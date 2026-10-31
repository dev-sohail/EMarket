<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAffiliateRequest;
use App\Http\Requests\Admin\UpdateAffiliateRequest;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Customer;
use App\Services\Affiliate\AffiliateService;
use App\Services\Shared\CsvSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AffiliateController extends Controller
{
    public function __construct(protected AffiliateService $affiliateService) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $affiliateStatus = trim((string) $request->input('affiliate_status', ''));
        $range = (int) $request->input('range', 30);

        if (! in_array($range, [7, 30, 90], true)) {
            $range = 30;
        }

        $affiliates = $this->affiliateService->getAffiliates(
            $search !== '' ? $search : null,
            $affiliateStatus !== '' ? $affiliateStatus : null
        );
        $commissions = $this->affiliateService->getCommissions($status !== '' ? $status : null);
        $stats = $this->affiliateService->stats();
        $settings = $this->affiliateService->getSettings();
        $availableCustomers = $this->affiliateService->getCustomersForSelect();
        $chart = $this->affiliateService->dailyCommissionsChart($range);
        $chartStatuses = $this->affiliateService->commissionStatuses();

        return view('admin.affiliate.index', compact(
            'affiliates',
            'commissions',
            'stats',
            'settings',
            'availableCustomers',
            'search',
            'status',
            'affiliateStatus',
            'chart',
            'chartStatuses',
            'range'
        ));
    }

    public function store(StoreAffiliateRequest $request): RedirectResponse
    {
        // No `(int)` cast: customer `_id` is a prefixed string (`cst_…`), and
        // casting it to int yields 0, so findOrFail(0) 404s on every enrollment.
        $customer = Customer::findOrFail($request->validated('customer_id'));
        $rate = $request->filled('commission_rate') ? (float) $request->input('commission_rate') : null;

        $this->affiliateService->enroll($customer, $rate);

        return redirect()->route('admin.affiliate.index')
            ->with('success', 'Customer enrolled as an affiliate.');
    }

    public function update(UpdateAffiliateRequest $request, Affiliate $affiliate): RedirectResponse
    {
        $this->affiliateService->update($affiliate, $request->validated());

        return redirect()->route('admin.affiliate.index')
            ->with('success', 'Affiliate updated successfully.');
    }

    public function destroy(Affiliate $affiliate): RedirectResponse
    {
        $this->affiliateService->delete($affiliate);

        return redirect()->route('admin.affiliate.index')
            ->with('success', 'Affiliate removed successfully.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'affiliate_commission_rate' => 'required|numeric|min:0|max:100',
            'affiliate_cookie_days' => 'required|integer|min:1|max:365',
            'affiliate_min_payout' => 'required|numeric|min:0',
        ]);

        $this->affiliateService->updateSettings($validated);

        return redirect()->route('admin.affiliate.index')
            ->with('success', 'Affiliate settings saved successfully.');
    }

    public function approveCommission(AffiliateCommission $commission): RedirectResponse
    {
        try {
            $this->affiliateService->approve($commission);
        } catch (\Exception $exception) {
            report($exception);

            return back()->with('error', 'Failed to approve commission. Please try again.');
        }

        return back()->with('success', 'Commission approved.');
    }

    public function rejectCommission(AffiliateCommission $commission): RedirectResponse
    {
        try {
            $this->affiliateService->reject($commission);
        } catch (\Exception $exception) {
            report($exception);

            return back()->with('error', 'Failed to reject commission. Please try again.');
        }

        return back()->with('success', 'Commission rejected.');
    }

    public function markCommissionPaid(Request $request, AffiliateCommission $commission): RedirectResponse
    {
        $validated = $request->validate([
            'reference' => 'nullable|string|max:255',
        ]);

        try {
            $this->affiliateService->markPaid($commission, $validated['reference'] ?? null);
        } catch (\Exception $exception) {
            report($exception);

            return back()->with('error', 'Failed to mark commission as paid. Please try again.');
        }

        return back()->with('success', 'Commission marked as paid.');
    }

    public function bulkApproveCommissions(): RedirectResponse
    {
        try {
            $count = $this->affiliateService->bulkApprove();
        } catch (\Exception $exception) {
            report($exception);

            return back()->with('error', 'Failed to approve commissions. Please try again.');
        }

        return back()->with('success', $count.' commission(s) approved.');
    }

    public function bulkPayApprovedCommissions(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reference' => 'nullable|string|max:255',
        ]);

        try {
            $count = $this->affiliateService->bulkMarkPaid($validated['reference'] ?? null);
        } catch (\Exception $exception) {
            report($exception);

            return back()->with('error', 'Failed to mark commissions as paid. Please try again.');
        }

        if ($count === 0) {
            return back()->with('error', 'There are no approved commissions to pay.');
        }

        return back()->with('success', $count.' commission(s) marked as paid.');
    }

    public function exportAffiliates(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Customer', 'Email', 'Status', 'Clicks', 'Rate %', 'Total Earned', 'Total Paid', 'Enrolled At']);

            Affiliate::query()->with('customer')->latest()->chunk(200, function ($affiliates) use ($handle) {
                foreach ($affiliates as $affiliate) {
                    fputcsv($handle, CsvSanitizer::row([
                        $affiliate->code,
                        $affiliate->customer->full_name ?? '',
                        $affiliate->customer->email ?? '',
                        $affiliate->status,
                        $affiliate->clicks,
                        $affiliate->commission_rate ?? '',
                        (float) $affiliate->total_earned,
                        (float) $affiliate->total_paid,
                        $affiliate->created_at?->format('Y-m-d H:i:s'),
                    ]));
                }
            });

            fclose($handle);
        }, 'affiliates-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportCommissions(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order', 'Affiliate', 'Email', 'Rate %', 'Amount', 'Status', 'Reference', 'Paid At', 'Created At']);

            AffiliateCommission::query()->with(['affiliate.customer', 'order'])->latest()->chunk(200, function ($commissions) use ($handle) {
                foreach ($commissions as $commission) {
                    fputcsv($handle, CsvSanitizer::row([
                        $commission->order->order_number ?? '',
                        $commission->affiliate->customer->full_name ?? '',
                        $commission->affiliate->customer->email ?? '',
                        $commission->rate,
                        (float) $commission->amount,
                        $commission->status,
                        $commission->reference ?? '',
                        $commission->paid_at?->format('Y-m-d H:i:s'),
                        $commission->created_at?->format('Y-m-d H:i:s'),
                    ]));
                }
            });

            fclose($handle);
        }, 'affiliate-commissions-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
