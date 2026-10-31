@extends('layouts.admin')
@section('title', 'Affiliates')
@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Affiliates</h1>
        <p class="text-sm text-[var(--color-steel)]">Manage affiliate partners, commissions and payouts.</p>
    </div>
    <span class="inline-flex px-3 py-1.5 text-xs font-medium rounded-full {{ affiliate_enabled() ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]' }}">
        {{ affiliate_enabled() ? 'Program Enabled' : 'Program Disabled' }}
    </span>
</div>
@endsection
@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-lg border border-[#1B7A43]/30 bg-[#1B7A43]/10 text-[#1B7A43] text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-lg border border-[var(--color-red)]/30 bg-[var(--color-red)]/10 text-[var(--color-red)] text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-[var(--color-paper)] rounded-lg border p-5">
            <p class="text-xs font-medium text-[var(--color-steel)] uppercase">Affiliates</p>
            <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ $stats['total_affiliates'] }}</p>
            <p class="text-xs text-[var(--color-steel)] mt-1">{{ $stats['active_affiliates'] }} active</p>
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border p-5">
            <p class="text-xs font-medium text-[var(--color-steel)] uppercase">Referral Clicks</p>
            <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ number_format($stats['total_clicks']) }}</p>
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border p-5">
            <p class="text-xs font-medium text-[var(--color-steel)] uppercase">Pending Commission</p>
            <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ number_format($stats['pending_commissions'], 2) }}</p>
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border p-5">
            <p class="text-xs font-medium text-[var(--color-steel)] uppercase">Paid Out</p>
            <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ number_format($stats['paid_commissions'], 2) }}</p>
        </div>
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
            <div>
                <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Commission Activity</h2>
                <p class="text-sm text-[var(--color-steel)] mt-0.5">{{ $chart['range_label'] }} · {{ $chart['days'] }} days</p>
            </div>
            <div class="flex items-center gap-1 rounded-lg border p-1">
                @foreach([7, 30, 90] as $option)
                    <a href="{{ route('admin.affiliate.index', array_filter([
                        'range' => $option === 30 ? null : $option,
                        'search' => $search !== '' ? $search : null,
                        'status' => $status !== '' ? $status : null,
                        'affiliate_status' => $affiliateStatus !== '' ? $affiliateStatus : null,
                    ])) }}"
                       class="px-3 py-1.5 text-xs font-medium rounded transition {{ $range === $option ? 'bg-[var(--color-ink)] text-white' : 'text-[var(--color-steel)] hover:bg-[var(--color-paper-dim)]' }}">
                        {{ $option }}d
                    </a>
                @endforeach
            </div>
        </div>

        <dl class="grid grid-cols-2 lg:grid-cols-4 gap-4 pb-6 mb-6 border-b hairline">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-[var(--color-steel)]">Total Commission</dt>
                <dd class="mt-1 font-display text-2xl font-semibold text-[var(--color-ink)]">{{ format_currency((float) $chart['summary']['total']) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-[var(--color-steel)]">Commissions</dt>
                <dd class="mt-1 font-display text-2xl font-semibold text-[var(--color-ink)]">{{ number_format($chart['summary']['count']) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-[var(--color-steel)]">Avg / Active Day</dt>
                <dd class="mt-1 font-display text-2xl font-semibold text-[var(--color-ink)]">{{ format_currency((float) $chart['summary']['average']) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-[var(--color-steel)]">Best Day</dt>
                <dd class="mt-1 font-display text-2xl font-semibold text-[var(--color-ink)]">{{ format_currency((float) $chart['summary']['peak']) }}</dd>
                @if($chart['summary']['peak_label'])
                    <p class="text-xs text-[var(--color-steel)] mt-0.5">{{ $chart['summary']['peak_label'] }}</p>
                @endif
            </div>
        </dl>

        @if($chart['summary']['count'] === 0)
            <div class="h-72 flex flex-col items-center justify-center rounded-lg border border-dashed border-[var(--color-line)] bg-[var(--color-paper-dim)] text-center px-6">
                <i class="fa-solid fa-chart-line text-2xl text-[var(--color-steel)]" aria-hidden="true"></i>
                <p class="mt-3 text-sm font-medium text-[var(--color-ink)]">No commissions in this period</p>
                <p class="mt-1 text-xs text-[var(--color-steel)]">Commission records will appear here once affiliates earn from an order.</p>
            </div>
        @else
            <div class="relative h-72 sm:h-80">
                <canvas id="affiliateCommissionsChart" aria-label="Daily commission totals by status" role="img"></canvas>
            </div>
        @endif
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <h2 class="font-semibold text-[var(--color-ink)] mb-4">Program Settings</h2>
        <form method="POST" action="{{ route('admin.affiliate.settings') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-medium text-[var(--color-steel)] uppercase mb-1">Default Commission (%)</label>
                <input type="number" name="affiliate_commission_rate" value="{{ $settings['affiliate_commission_rate'] }}" step="0.01" min="0" max="100" required class="w-full rounded-lg border-[var(--color-line)] bg-[var(--color-paper-dim)] text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--color-steel)] uppercase mb-1">Cookie Lifetime (days)</label>
                <input type="number" name="affiliate_cookie_days" value="{{ $settings['affiliate_cookie_days'] }}" min="1" max="365" required class="w-full rounded-lg border-[var(--color-line)] bg-[var(--color-paper-dim)] text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--color-steel)] uppercase mb-1">Minimum Payout</label>
                <input type="number" name="affiliate_min_payout" value="{{ $settings['affiliate_min_payout'] }}" step="0.01" min="0" required class="w-full rounded-lg border-[var(--color-line)] bg-[var(--color-paper-dim)] text-sm">
            </div>
            <button type="submit" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition">Save Settings</button>
        </form>
        @error('affiliate_commission_rate')<p class="text-xs text-[var(--color-red)] mt-2">{{ $message }}</p>@enderror
        @error('affiliate_cookie_days')<p class="text-xs text-[var(--color-red)] mt-2">{{ $message }}</p>@enderror
        @error('affiliate_min_payout')<p class="text-xs text-[var(--color-red)] mt-2">{{ $message }}</p>@enderror
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <h2 class="font-semibold text-[var(--color-ink)] mb-4">Enroll Affiliate</h2>
        <form method="POST" action="{{ route('admin.affiliate.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            @csrf
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-[var(--color-steel)] uppercase mb-1">Customer</label>
                <select name="customer_id" required class="w-full rounded-lg border-[var(--color-line)] bg-[var(--color-paper-dim)] text-sm">
                    <option value="">— Select customer —</option>
                    @forelse($availableCustomers as $customer)
                        <option value="{{ $customer->getKey() }}">{{ $customer->full_name }} ({{ $customer->email }})</option>
                    @empty
                        <option value="">All customers are enrolled</option>
                    @endforelse
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--color-steel)] uppercase mb-1">Commission Override (%)</label>
                <input type="number" name="commission_rate" step="0.01" min="0" max="100" placeholder="Default: {{ $settings['affiliate_commission_rate'] }}" class="w-full rounded-lg border-[var(--color-line)] bg-[var(--color-paper-dim)] text-sm">
            </div>
            <button type="submit" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition">Enroll</button>
        </form>
        @error('customer_id')<p class="text-xs text-[var(--color-red)] mt-2">{{ $message }}</p>@enderror
        @error('commission_rate')<p class="text-xs text-[var(--color-red)] mt-2">{{ $message }}</p>@enderror
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
        <div class="p-4 border-b flex items-center justify-between gap-4 flex-wrap">
            <h2 class="font-semibold text-[var(--color-ink)]">Affiliates</h2>
            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center gap-2 text-sm">
                    @foreach(['' => 'All', 'active' => 'Active', 'suspended' => 'Suspended', 'pending' => 'Pending'] as $value => $label)
                        <a href="{{ route('admin.affiliate.index', $value === '' ? [] : ['affiliate_status' => $value]) }}" class="px-3 py-1.5 rounded-lg {{ $affiliateStatus === $value ? 'bg-[var(--color-red)] text-white' : 'border hover:bg-[var(--color-paper-dim)]' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <form method="GET" action="{{ route('admin.affiliate.index') }}" class="flex items-center gap-2">
                    @if($affiliateStatus !== '')
                        <input type="hidden" name="affiliate_status" value="{{ $affiliateStatus }}">
                    @endif
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search name, email or code" class="rounded-lg border-[var(--color-line)] bg-[var(--color-paper-dim)] text-sm px-3 py-1.5">
                    <button type="submit" class="px-3 py-1.5 rounded-lg border text-sm hover:bg-[var(--color-paper-dim)]">Search</button>
                </form>
                <a href="{{ route('admin.affiliate.export') }}" class="px-3 py-1.5 rounded-lg border text-sm hover:bg-[var(--color-paper-dim)]">Export CSV</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-[var(--color-paper-dim)]">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Affiliate</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Code</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Clicks</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Rate %</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Earned</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Paid</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Balance</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Status</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($affiliates as $affiliate)
                        <tr class="hover:bg-[var(--color-paper-dim)]">
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-[var(--color-ink)]">{{ $affiliate->customer->full_name }}</p>
                                <p class="text-xs text-[var(--color-steel)]">{{ $affiliate->customer->email }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-mono text-sm text-[var(--color-ink)]">{{ $affiliate->code }}</span>
                                <div class="flex items-center gap-1 mt-1">
                                    <input type="text" readonly data-action="select-text" value="{{ route('referral.go', $affiliate->code) }}" class="w-44 text-[10px] text-[var(--color-steel)] bg-[var(--color-paper-dim)] border rounded px-1 py-0.5 font-mono">
                                    <button type="button" data-action="copy-text" data-copy-label="Copy" data-copy-text="{{ route('referral.go', $affiliate->code) }}" class="text-[10px] border rounded px-1.5 py-0.5 hover:bg-[var(--color-paper-dim)]">Copy</button>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $affiliate->clicks }}</td>
                            <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $affiliate->commission_rate ?? 'Default' }}</td>
                            <td class="px-6 py-4 text-sm font-medium">{{ number_format((float) $affiliate->total_earned, 2) }}</td>
                            <td class="px-6 py-4 text-sm font-medium">{{ number_format((float) $affiliate->total_paid, 2) }}</td>
                            <td class="px-6 py-4 text-sm font-medium">
                                @php $balance = max(0.0, (float) $affiliate->total_earned - (float) $affiliate->total_paid); @endphp
                                {{ number_format($balance, 2) }}
                                @if($balance > 0 && $balance >= affiliate_min_payout())
                                    <span class="ml-1 inline-flex px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-[#1B7A43]/10 text-[#1B7A43]">Payout ready</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $affiliate->status === 'active' ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : ($affiliate->status === 'suspended' ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]') }}">{{ ucfirst($affiliate->status) }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.affiliate.update', $affiliate) }}" class="flex items-center gap-2">
                                        @csrf @method('PUT')
                                        <input type="number" name="commission_rate" value="{{ $affiliate->commission_rate }}" step="0.01" min="0" max="100" placeholder="—" class="w-20 rounded border-[var(--color-line)] bg-[var(--color-paper-dim)] text-xs px-2 py-1">
                                        <select name="status" class="rounded border-[var(--color-line)] bg-[var(--color-paper-dim)] text-xs px-2 py-1">
                                            @foreach(['pending', 'active', 'suspended'] as $option)
                                                <option value="{{ $option }}" {{ $affiliate->status === $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.affiliate.destroy', $affiliate) }}" data-confirm="Remove this affiliate? Their commissions are removed too.">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-6 py-8 text-center text-[var(--color-steel)]">No affiliates enrolled yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($affiliates, 'links'))
            <div class="p-4 border-t">{{ $affiliates->links() }}</div>
        @endif
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
        <div class="p-4 border-b flex items-center justify-between gap-4 flex-wrap">
            <h2 class="font-semibold text-[var(--color-ink)]">Commissions</h2>
            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center gap-2 text-sm">
                    @foreach(['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected'] as $value => $label)
                        <a href="{{ route('admin.affiliate.index', $value === '' ? [] : ['status' => $value]) }}" class="px-3 py-1.5 rounded-lg {{ $status === $value ? 'bg-[var(--color-red)] text-white' : 'border hover:bg-[var(--color-paper-dim)]' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('admin.affiliate.commissions.approveAll') }}" data-confirm="Approve all pending commissions?">
                    @csrf
                    <button type="submit" {{ $stats['pending_commissions_count'] === 0 ? 'disabled' : '' }} class="px-3 py-1.5 rounded-lg border text-sm hover:bg-[var(--color-paper-dim)] disabled:opacity-50 disabled:cursor-not-allowed">Approve all pending ({{ $stats['pending_commissions_count'] }})</button>
                </form>
                <form method="POST" action="{{ route('admin.affiliate.commissions.payApproved') }}" data-confirm="Mark all approved commissions as paid?" class="flex items-center gap-1">
                    @csrf
                    <input type="text" name="reference" placeholder="Reference" class="rounded border-[var(--color-line)] bg-[var(--color-paper-dim)] text-xs px-2 py-1.5">
                    <button type="submit" {{ $stats['approved_commissions_count'] === 0 ? 'disabled' : '' }} class="px-3 py-1.5 rounded-lg border text-sm hover:bg-[var(--color-paper-dim)] disabled:opacity-50 disabled:cursor-not-allowed">Pay all approved ({{ $stats['approved_commissions_count'] }})</button>
                </form>
                <a href="{{ route('admin.affiliate.commissions.export') }}" class="px-3 py-1.5 rounded-lg border text-sm hover:bg-[var(--color-paper-dim)]">Export CSV</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-[var(--color-paper-dim)]">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Order</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Affiliate</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Rate %</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Amount</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Status</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Reference</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($commissions as $commission)
                        <tr class="hover:bg-[var(--color-paper-dim)]">
                            <td class="px-6 py-4 text-sm font-medium text-[var(--color-ink)]">{{ $commission->order->order_number ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm">{{ $commission->affiliate->customer->full_name ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $commission->rate }}</td>
                            <td class="px-6 py-4 text-sm font-medium">{{ number_format((float) $commission->amount, 2) }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $commission->status === 'paid' ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : ($commission->status === 'rejected' ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]') }}">{{ ucfirst($commission->status) }}</span>
                            </td>
                            <td class="px-6 py-4 text-xs text-[var(--color-steel)]">{{ $commission->reference ?? '—' }}</td>
                            <td class="px-6 py-4 text-right">
                                @if($commission->status === 'pending')
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.affiliate.commissions.approve', $commission) }}">
                                            @csrf
                                            <button type="submit" class="text-[#1B7A43] hover:underline text-sm">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.affiliate.commissions.reject', $commission) }}">
                                            @csrf
                                            <button type="submit" class="text-[var(--color-red)] hover:underline text-sm">Reject</button>
                                        </form>
                                    </div>
                                @elseif($commission->status === 'approved')
                                    <form method="POST" action="{{ route('admin.affiliate.commissions.markPaid', $commission) }}" class="flex items-center justify-end gap-2">
                                        @csrf
                                        <input type="text" name="reference" placeholder="Payment reference" class="rounded border-[var(--color-line)] bg-[var(--color-paper-dim)] text-xs px-2 py-1">
                                        <button type="submit" class="bg-[var(--color-red)] text-white px-3 py-1.5 rounded text-xs hover:bg-[var(--color-red-dark)]">Mark Paid</button>
                                    </form>
                                @else
                                    <span class="text-xs text-[var(--color-steel)]">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-8 text-center text-[var(--color-steel)]">No commissions recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($commissions, 'links'))
            <div class="p-4 border-t">{{ $commissions->links() }}</div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('affiliateCommissionsChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const style = getComputedStyle(document.documentElement);
    const token = (name, fallback) => (style.getPropertyValue(name) || fallback).trim();

    const ink = token('--color-ink', '#14171a');
    const steel = token('--color-steel', '#6b6f76');
    const red = token('--color-red', '#d6001c');
    const sale = token('--color-sale', '#1b7a43');
    const line = token('--color-line', '#dedcd4');

    const palette = {
        pending: red,
        approved: ink,
        paid: sale,
        rejected: steel,
    };

    const labels = {
        pending: 'Pending',
        approved: 'Approved',
        paid: 'Paid',
        rejected: 'Rejected',
    };

    const series = @json($chart['series']);
    const statuses = @json($chartStatuses);
    const counts = @json($chart['counts']);

    const datasets = statuses.map((status, index) => ({
        label: labels[status] || status,
        data: series[status] || [],
        backgroundColor: palette[status] || steel,
        hoverBackgroundColor: palette[status] || steel,
        borderWidth: 0,
        borderRadius: 2,
        borderSkipped: false,
        maxBarThickness: 28,
        categoryPercentage: 0.8,
        barPercentage: 0.92,
        stack: 'commission',
        order: statuses.length - index,
    }));

    const dates = @json($chart['dates']);
    const totals = @json($chart['totals']);

    let money;
    try {
        money = new Intl.NumberFormat(document.documentElement.lang || 'en', {
            style: 'currency',
            currency: @json(currency() ?: 'USD'),
        });
    } catch {
        money = new Intl.NumberFormat(document.documentElement.lang || 'en', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: @json($chart['labels']),
            datasets: datasets,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            layout: { padding: { top: 4 } },
            scales: {
                x: {
                    stacked: true,
                    grid: { display: false },
                    border: { color: line },
                    ticks: {
                        color: steel,
                        maxRotation: 0,
                        autoSkipPadding: 16,
                        maxTicksLimit: @json($chart['days'] <= 7 ? 7 : 14),
                    },
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    grid: { color: line, drawBorder: false },
                    border: { display: false },
                    ticks: {
                        color: steel,
                        maxTicksLimit: 6,
                        callback: (value) => money.format(value),
                    },
                },
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: { color: steel, usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 10, boxHeight: 10, padding: 16 },
                },
                tooltip: {
                    callbacks: {
                        title: (items) => {
                            const index = items[0]?.dataIndex;
                            const date = dates[index];
                            return date ? new Date(date + 'T00:00:00').toLocaleDateString(document.documentElement.lang || 'en', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) : (items[0]?.label ?? '');
                        },
                        label: (context) => ` ${context.dataset.label}: ${money.format(context.parsed.y)}`,
                        footer: (items) => {
                            const index = items[0]?.dataIndex;
                            const dayTotal = totals[index] ?? 0;
                            const dayCount = counts[index] ?? 0;
                            return `Total: ${money.format(dayTotal)} · ${dayCount} commission${dayCount === 1 ? '' : 's'}`;
                        },
                    },
                    footerColor: steel,
                    footerFont: { weight: 'normal' },
                    boxPadding: 4,
                    usePointStyle: true,
                },
            },
        },
    });
});
</script>
@endpush