<?php

namespace App\Services\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AffiliateService
{
    public function __construct(
        protected Affiliate $affiliate,
        protected AffiliateCommission $commission
    ) {}

    /**
     * Enroll a customer as an affiliate, restoring a previously removed one.
     * Self applications use the pending status until an admin approves them.
     */
    public function enroll(Customer $customer, ?float $commissionRate = null, string $status = 'active'): Affiliate
    {
        return DB::transaction(function () use ($customer, $commissionRate, $status) {
            $existing = $this->affiliate->withTrashed()->where('customer_id', $customer->getKey())->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->update([
                    'status' => $status,
                    'commission_rate' => $commissionRate ?? $existing->commission_rate,
                ]);

                return $existing->fresh();
            }

            return $this->affiliate->create([
                'customer_id' => $customer->getKey(),
                'code' => $this->generateCode($customer),
                'status' => $status,
                'commission_rate' => $commissionRate,
            ]);
        });
    }

    public function generateCode(Customer $customer): string
    {
        $name = trim($customer->first_name.' '.$customer->last_name);
        $base = Str::upper(Str::substr(Str::slug($name !== '' ? $name : 'AFF'), 0, 4));
        $base = $base !== '' ? $base : 'AFF';

        do {
            $code = $base.Str::upper(Str::random(4));
        } while ($this->affiliate->withTrashed()->where('code', $code)->exists());

        return $code;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Affiliate $affiliate, array $data): Affiliate
    {
        $affiliate->update($data);

        return $affiliate->fresh();
    }

    /**
     * Remove an affiliate and reject any of their pending commissions, so no
     * unresolvable rows are left in the ledger. Approved and paid commissions
     * are kept as history.
     */
    public function delete(Affiliate $affiliate): bool
    {
        return DB::transaction(function () use ($affiliate) {
            $affiliate->commissions()
                ->where('status', 'pending')
                ->each(fn (AffiliateCommission $commission) => $this->reject($commission));

            return $affiliate->delete();
        });
    }

    public function recordClick(Affiliate $affiliate): void
    {
        $affiliate->increment('clicks');
    }

    public function findActiveByCode(string $code): ?Affiliate
    {
        return $this->affiliate->where('code', strtoupper(trim($code)))->active()->first();
    }

    public function findByCustomer(Customer $customer): ?Affiliate
    {
        return $this->affiliate->where('customer_id', $customer->getKey())->first();
    }

    /**
     * @return LengthAwarePaginator<int, AffiliateCommission>
     */
    public function getCustomerCommissions(Affiliate $affiliate, int $perPage = 10): LengthAwarePaginator
    {
        return $affiliate->commissions()
            ->with('order')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @return array{clicks: int, total_earned: float, total_paid: float, balance: float, pending_amount: float, paid_count: int, commission_count: int}
     */
    public function customerStats(Affiliate $affiliate): array
    {
        $agg = $affiliate->commissions()
            ->selectRaw("SUM(CASE WHEN status='pending' THEN amount ELSE 0 END) as pending_amount")
            ->selectRaw("SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) as paid_count")
            ->selectRaw('COUNT(*) as commission_count')
            ->first();

        return [
            'clicks' => (int) $affiliate->clicks,
            'total_earned' => (float) $affiliate->total_earned,
            'total_paid' => (float) $affiliate->total_paid,
            'balance' => max(0.0, (float) $affiliate->total_earned - (float) $affiliate->total_paid),
            'pending_amount' => (float) ($agg->pending_amount ?? 0),
            'paid_count' => (int) ($agg->paid_count ?? 0),
            'commission_count' => (int) ($agg->commission_count ?? 0),
        ];
    }

    /**
     * Attribute an order to the affiliate whose referral code sits in the
     * ref_code cookie. Never throws — checkout must not fail because of
     * affiliate tracking.
     */
    public function attributeOrder(Order $order): ?AffiliateCommission
    {
        try {
            if (! affiliate_enabled()) {
                return null;
            }

            $code = (string) request()->cookie('ref_code', '');

            if ($code === '') {
                return null;
            }

            $affiliate = $this->findActiveByCode($code);

            if (! $affiliate) {
                return null;
            }

            if ($order->customer_id && $order->customer_id === $affiliate->customer_id) {
                return null;
            }

            if ($this->commission->where('order_id', $order->getKey())->exists()) {
                return null;
            }

            $rate = $affiliate->effectiveRate(affiliate_commission_rate());
            $netBase = (float) $order->subtotal - (float) $order->discount;
            if ($netBase < 0) {
                $netBase = 0;
            }
            $amount = round($netBase * ($rate / 100), 2, PHP_ROUND_HALF_UP);

            if ($amount <= 0) {
                return null;
            }

            $commission = $this->commission->create([
                'affiliate_id' => $affiliate->getKey(),
                'order_id' => $order->getKey(),
                'amount' => $amount,
                'rate' => $rate,
                'status' => 'pending',
            ]);

            $affiliate->increment('total_earned', $amount);

            return $commission;
        } catch (\Throwable $e) {
            Log::error('Affiliate order attribution failed.', [
                'order_id' => $order->getKey(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * @throws \Exception when the commission is not pending.
     */
    public function approve(AffiliateCommission $commission): AffiliateCommission
    {
        if ($commission->status !== 'pending') {
            throw new \Exception('Only pending commissions can be approved.');
        }

        $commission->update(['status' => 'approved']);

        return $commission->fresh();
    }

    /**
     * Both pending and approved commissions count towards total_earned, so
     * rejecting either removes its amount exactly once. Commissions already
     * rejected or paid cannot change.
     *
     * @throws \Exception when the commission is rejected or paid.
     */
    public function reject(AffiliateCommission $commission): AffiliateCommission
    {
        if (in_array($commission->status, ['rejected', 'paid'], true)) {
            throw new \Exception('Only pending or approved commissions can be rejected.');
        }

        $affiliate = $commission->affiliate;

        DB::transaction(function () use ($commission, $affiliate) {
            $affiliate->decrement('total_earned', (float) $commission->amount);
            $commission->update(['status' => 'rejected']);
        });

        return $commission->fresh();
    }

    /**
     * @throws \Exception when the commission is not approved.
     */
    public function markPaid(AffiliateCommission $commission, ?string $reference = null): AffiliateCommission
    {
        if ($commission->status !== 'approved') {
            throw new \Exception('Only approved commissions can be marked as paid.');
        }

        $affiliate = $commission->affiliate;

        DB::transaction(function () use ($commission, $affiliate, $reference) {
            $affiliate->increment('total_paid', (float) $commission->amount);
            $commission->update([
                'status' => 'paid',
                'reference' => $reference,
                'paid_at' => now(),
            ]);
        });

        return $commission->fresh();
    }

    /**
     * @param  array<string, string|numeric>  $data
     */
    public function updateSettings(array $data): void
    {
        foreach ($data as $key => $value) {
            Setting::put($key, $value, 'affiliate');
        }

        setting_forget();
    }

    /**
     * @return array{affiliate_commission_rate: float, affiliate_cookie_days: int, affiliate_min_payout: float}
     */
    public function getSettings(): array
    {
        return [
            'affiliate_commission_rate' => affiliate_commission_rate(),
            'affiliate_cookie_days' => affiliate_cookie_days(),
            'affiliate_min_payout' => affiliate_min_payout(),
        ];
    }

    /**
     * @return array{total_affiliates: int, active_affiliates: int, pending_commissions: float, approved_commissions: float, paid_commissions: float, total_clicks: int, pending_commissions_count: int, approved_commissions_count: int}
     */
    public function stats(): array
    {
        return [
            'total_affiliates' => $this->affiliate->count(),
            'active_affiliates' => $this->affiliate->where('status', 'active')->count(),
            'pending_commissions' => (float) $this->commission->where('status', 'pending')->sum('amount'),
            'approved_commissions' => (float) $this->commission->where('status', 'approved')->sum('amount'),
            'paid_commissions' => (float) $this->commission->where('status', 'paid')->sum('amount'),
            'total_clicks' => (int) $this->affiliate->sum('clicks'),
            'pending_commissions_count' => (int) $this->commission->where('status', 'pending')->count(),
            'approved_commissions_count' => (int) $this->commission->where('status', 'approved')->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Affiliate>
     */
    public function getAffiliates(?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->affiliate->query()
            ->with('customer')
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== null && $search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, AffiliateCommission>
     */
    public function getCommissions(?string $status = null): LengthAwarePaginator
    {
        return $this->commission->query()
            ->with(['affiliate.customer', 'order'])
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * Approve every pending commission at once.
     */
    public function bulkApprove(): int
    {
        return DB::transaction(function () {
            $count = 0;

            $this->commission->query()
                ->where('status', 'pending')
                ->orderBy('_id')
                ->each(function (AffiliateCommission $commission) use (&$count) {
                    $this->approve($commission);
                    $count++;
                });

            return $count;
        });
    }

    /**
     * Pay every approved commission at once.
     */
    public function bulkMarkPaid(?string $reference = null): int
    {
        return DB::transaction(function () use ($reference) {
            $count = 0;

            $this->commission->query()
                ->where('status', 'approved')
                ->orderBy('_id')
                ->each(function (AffiliateCommission $commission) use (&$count, $reference) {
                    $this->markPaid($commission, $reference);
                    $count++;
                });

            return $count;
        });
    }

    /**
     * Commission statuses, in the order the chart stacks them.
     *
     * @return array<int, string>
     */
    public function commissionStatuses(): array
    {
        return ['pending', 'approved', 'paid', 'rejected'];
    }

    /**
     * Daily commission totals for the last N days, split by status.
     *
     * Aggregated in SQL rather than by loading every row in the window: the
     * table grows with every order and only three numbers per day are needed.
     *
     * @return array{
     *     days: int,
     *     range_label: string,
     *     labels: array<int, string>,
     *     dates: array<int, string>,
     *     series: array<string, array<int, float>>,
     *     counts: array<int, int>,
     *     totals: array<int, float>,
     *     summary: array{total: float, count: int, active_days: int, average: float, peak: float, peak_label: string|null}
     * }
     */
    public function dailyCommissionsChart(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $statuses = $this->commissionStatuses();
        $today = now()->startOfDay();
        $start = $today->copy()->subDays($days - 1);

        $rows = $this->commission->newQuery()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as commission_day, status, COUNT(*) as aggregate_count, SUM(amount) as aggregate_amount')
            ->groupBy('commission_day')
            ->groupBy('status')
            ->get();

        $byDay = [];
        foreach ($rows as $row) {
            $day = (string) ($row->commission_day ?? substr((string) $row->created_at, 0, 10));
            $status = (string) $row->status;

            if (! in_array($status, $statuses, true)) {
                $status = 'pending';
            }

            $byDay[$day][$status] = [
                'amount' => (float) $row->aggregate_amount,
                'count' => (int) $row->aggregate_count,
            ];
        }

        $labels = [];
        $dates = [];
        $series = array_fill_keys($statuses, []);
        $counts = [];
        $totals = [];
        $dailyTotals = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day = $today->copy()->subDays($offset);
            $key = $day->format('Y-m-d');
            $dayTotal = 0.0;
            $dayCount = 0;

            $labels[] = $day->format('M j');
            $dates[] = $key;

            foreach ($statuses as $status) {
                $amount = (float) ($byDay[$key][$status]['amount'] ?? 0.0);
                $series[$status][] = $amount;
                $dayTotal += $amount;
                $dayCount += (int) ($byDay[$key][$status]['count'] ?? 0);
            }

            $counts[] = $dayCount;
            $totals[] = $dayTotal;
            $dailyTotals[$key] = $dayTotal;
        }

        $total = array_sum($totals);
        $count = array_sum($counts);
        $activeDays = count(array_filter($dailyTotals, static fn (float $value): bool => $value > 0));
        $peakDay = $dailyTotals === []
            ? null
            : (string) array_search(max($dailyTotals), $dailyTotals, true);

        return [
            'days' => $days,
            'range_label' => $start->format('M j').' – '.$today->format('M j, Y'),
            'labels' => $labels,
            'dates' => $dates,
            'series' => $series,
            'counts' => $counts,
            'totals' => $totals,
            'summary' => [
                'total' => $total,
                'count' => $count,
                'active_days' => $activeDays,
                'average' => $activeDays > 0 ? $total / $activeDays : 0.0,
                'peak' => $peakDay !== null ? $dailyTotals[$peakDay] : 0.0,
                'peak_label' => $peakDay !== null && $dailyTotals[$peakDay] > 0
                    ? Carbon::createFromFormat('Y-m-d', $peakDay)->format('M j')
                    : null,
            ],
        ];
    }

    /**
     * Customers that are not enrolled as affiliates yet.
     *
     * @return Collection<int, Customer>
     */
    public function getCustomersForSelect(): Collection
    {
        return Customer::query()
            ->whereDoesntHave('affiliate')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }
}
