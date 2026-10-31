@extends('layouts.admin')

@section('title', 'Order Processor')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Order Processor</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">
            {{ $statusCounts[$status] ?? 0 }} {{ strtolower($statuses[$status]['label'] ?? 'matching') }} order{{ ($statusCounts[$status] ?? 0) === 1 ? '' : 's' }} in this stage
        </p>
    </div>
    <a href="{{ route('admin.orders.index') }}" class="px-4 py-2 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition flex items-center gap-2">
        <i class="fa-solid fa-receipt"></i>
        All Orders
    </a>
</div>
@endsection

@section('content')
@php
    /**
     * Badge tone per status.
     *
     * These have to be literal class strings. The previous version assembled
     * them as `bg-{{ $color }}-100`, which Tailwind cannot see: it compiles by
     * scanning source text, so any colour that appears nowhere else in the
     * codebase never makes it into the stylesheet. `indigo` and `cyan` were
     * exactly that, and those two pills rendered with no colour at all.
     */
    $statusTone = fn (?string $value): string => match ($value) {
        'pending' => 'bg-[#F59E0B]/10 text-[#B45309]',
        'confirmed' => 'bg-[#2563EB]/10 text-[#2563EB]',
        'processing' => 'bg-[var(--color-red)]/10 text-[var(--color-red-dark)]',
        'ready_to_ship' => 'bg-[#7C3AED]/10 text-[#7C3AED]',
        'shipped' => 'bg-[#0E7490]/10 text-[#0E7490]',
        'out_for_delivery' => 'bg-[#0F766E]/10 text-[#0F766E]',
        'delivered', 'completed' => 'bg-[#1B7A43]/10 text-[#1B7A43]',
        'returned' => 'bg-[#7C3AED]/10 text-[#7C3AED]',
        'cancelled' => 'bg-[var(--color-steel)]/15 text-[var(--color-steel)]',
        default => 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]',
    };

    $statusIcon = fn (?string $value): string => match ($value) {
        'pending' => 'fa-clock',
        'confirmed' => 'fa-check',
        'processing' => 'fa-gear',
        'ready_to_ship' => 'fa-box-open',
        'shipped' => 'fa-truck',
        'out_for_delivery' => 'fa-truck-fast',
        'delivered', 'completed' => 'fa-check-double',
        'cancelled' => 'fa-ban',
        default => 'fa-receipt',
    };

    // The next legal stage, used for the row's advance button. Mirrors the
    // forward path in OrderService::ALLOWED_TRANSITIONS.
    $nextStage = [
        'pending' => 'confirmed',
        'confirmed' => 'processing',
        'processing' => 'ready_to_ship',
        'ready_to_ship' => 'shipped',
        'shipped' => 'out_for_delivery',
        'out_for_delivery' => 'delivered',
    ];

    $statusLabel = fn (?string $value): string => $statuses[$value]['label'] ?? $statuses['pending']['label'];

    // Tabs carry the search and page size forward instead of resetting to a
    // bare `?status=`, which previously threw the search away.
    $tabQuery = request()->only(['search', 'per_page']);
@endphp

<div class="space-y-6">
    <nav data-tabs class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
        <div class="flex overflow-x-auto">
            @foreach($statuses as $key => $info)
                <a href="{{ route('admin.order-processor.index', $tabQuery + ['status' => $key]) }}"
                   @if($status === $key) aria-current="page" @endif
                   class="flex items-center gap-2 px-5 py-3.5 text-sm font-medium border-b-2 whitespace-nowrap transition {{ $status === $key ? 'border-[var(--color-red)] text-[var(--color-red)] bg-[var(--color-red)]/5' : 'border-transparent text-[var(--color-steel)] hover:text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)]' }}">
                    <i class="fa-solid {{ $info['icon'] }}"></i>
                    {{ $info['label'] }}
                    <span class="ml-1 px-2 py-0.5 text-xs rounded-full {{ $status === $key ? 'bg-[var(--color-red)] text-white' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}">
                        {{ $statusCounts[$key] ?? 0 }}
                    </span>
                </a>
            @endforeach
        </div>
    </nav>

    <form method="GET" action="{{ route('admin.order-processor.index') }}" class="bg-[var(--color-paper)] rounded-lg border hairline p-4 flex flex-wrap items-center gap-3">
        <input type="hidden" name="status" value="{{ $status }}">

        <div class="relative flex-1 min-w-[220px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-steel)] text-sm"></i>
            <input type="search" name="search" value="{{ $search ?? '' }}" placeholder="Search by order #, customer name or email&hellip;"
                   class="w-full border hairline bg-[var(--color-paper)] pl-9 pr-4 py-2.5 text-sm rounded-lg focus:outline-none focus:border-[var(--color-ink)]">
        </div>

        <select name="per_page" data-auto-submit aria-label="Results per page" class="border hairline bg-[var(--color-paper)] px-3 py-2.5 text-sm rounded-lg focus:outline-none focus:border-[var(--color-ink)]">
            @foreach($perPageOptions ?? [20, 50, 100] as $option)
                <option value="{{ $option }}" @selected(($perPage ?? 20) === $option)>{{ $option }} / page</option>
            @endforeach
        </select>

        <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">Search</button>

        @if($search)
            <a href="{{ route('admin.order-processor.index', ['status' => $status, 'per_page' => $perPage]) }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Clear</a>
        @endif
    </form>

    <form method="POST" action="{{ route('admin.order-processor.bulk') }}" id="bulk-form" data-bulk-form>
        @csrf

        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 flex flex-wrap items-center justify-between gap-4 mb-4 sticky top-0 z-10">
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-sm text-[var(--color-steel)] cursor-pointer">
                    <input type="checkbox" id="select-all" class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                    Select all on this page
                </label>
                <span id="selected-count" class="text-sm text-[var(--color-steel)]">Nothing selected</span>
            </div>
            <div class="flex items-center gap-3">
                {{-- `required` rather than an empty first option: with no
                     target chosen the old form submitted `status=""` and came
                     back with a generic "required" rule failure. --}}
                <select name="status" required data-bulk-status aria-label="Move selected orders to"
                        class="border hairline bg-[var(--color-paper)] px-3 py-2 text-sm rounded-lg focus:outline-none focus:border-[var(--color-ink)]">
                    <option value="" selected disabled>Move to&hellip;</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="processing">Processing</option>
                    <option value="ready_to_ship">Ready to Ship</option>
                    <option value="shipped">Shipped</option>
                    <option value="out_for_delivery">Out for Delivery</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button type="submit" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition disabled:opacity-50 disabled:cursor-not-allowed" id="bulk-submit" disabled>
                    Apply
                </button>
            </div>
        </div>

        <div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-[var(--color-paper-dim)]">
                        <tr>
                            <th class="w-12 px-4 py-3"></th>
                            <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Order</th>
                            <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Customer</th>
                            <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Items</th>
                            <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Total</th>
                            <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Date</th>
                            <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                            <th class="text-right px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-line)]">
                        @forelse($orders as $order)
                            @php
                                $statusValue = $order->status?->value;
                                // An order is verified when it is paid and has
                                // enough address detail to actually ship it.
                                $isVerified = $order->payment_status?->value === 'paid'
                                    && ! empty($order->shippingAddress?->full_name)
                                    && ! empty($order->shippingAddress?->address_line_1)
                                    && ! empty($order->shippingAddress?->city);
                                $isTerminal = in_array($statusValue, ['delivered', 'completed', 'cancelled', 'returned'], true);
                                $advanceTo = $nextStage[$statusValue] ?? null;
                                $customerName = $order->customer?->full_name ?? $order->user?->name;
                            @endphp
                            <tr class="hover:bg-[var(--color-paper-dim)] transition-colors">
                                <td class="px-4 py-4">
                                    <input type="checkbox" name="order_ids[]" value="{{ $order->getKey() }}" class="order-checkbox rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                                </td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.orders.show', $order->getKey()) }}" class="font-medium text-sm text-[var(--color-ink)] hover:text-[var(--color-red)]">
                                        #{{ $order->order_number }}
                                    </a>
                                    <p class="text-xs text-[var(--color-steel)] mt-0.5">{{ $order->payment_method_label }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="text-sm text-[var(--color-ink)]">{{ $customerName ?? 'Guest checkout' }}</p>
                                    <p class="text-xs text-[var(--color-steel)]">{{ $order->customer?->email ?: $order->user?->email ?? '-' }}</p>
                                </td>
                                <td class="px-4 py-4 text-sm text-[var(--color-steel)]">{{ $order->items->count() }}</td>
                                <td class="px-4 py-4 text-sm font-medium text-[var(--color-ink)] price">{{ format_currency($order->grand_total) }}</td>
                                <td class="px-4 py-4 text-sm text-[var(--color-steel)] whitespace-nowrap">
                                    <span class="block">{{ $order->created_at->format('M d, Y') }}</span>
                                    <span class="block text-xs">{{ $order->created_at->format('H:i') }}</span>
                                </td>
                                <td class="px-4 py-4">
                                    <span data-status="{{ $statusValue }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $statusTone($statusValue) }}">
                                        <i class="fa-solid {{ $statusIcon($statusValue) }} text-[10px]"></i>
                                        {{ $statusLabel($statusValue) }}
                                    </span>

                                    <div class="mt-1.5">
                                        @if($isVerified)
                                            <span class="inline-flex items-center gap-1 text-[10px] text-[#1B7A43] font-medium">
                                                <i class="fa-solid fa-circle-check"></i> Verified
                                            </span>
                                        @elseif(! $isTerminal)
                                            <span class="inline-flex items-center gap-1 text-[10px] text-[var(--color-red)] font-medium">
                                                <i class="fa-solid fa-circle-exclamation"></i> Needs review
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.orders.show', $order->getKey()) }}" class="text-[var(--color-steel)] hover:text-[var(--color-red)] text-sm p-1.5 rounded hover:bg-[var(--color-paper-dim)]" title="View order"><i class="fa-solid fa-eye"></i></a>
                                        <a href="{{ route('admin.orders.print', $order->getKey()) }}" target="_blank" class="text-[var(--color-steel)] hover:text-[var(--color-red)] text-sm p-1.5 rounded hover:bg-[var(--color-paper-dim)]" title="Print"><i class="fa-solid fa-print"></i></a>

                                        @unless($isTerminal)
                                            {{-- Dispatched through the shared data-action
                                                     dispatcher: the previous
                                                     inline @click was blocked
                                                     by the nonce CSP, so this
                                                     button did nothing.

                                                     The argument list has to be
                                                     real JSON, because
                                                     dispatchAction() calls
                                                     JSON.parse on it. Built with
                                                     json_encode rather than
                                                     Js::from, which renders a
                                                     bare string as PHP-style
                                                     'quoted' - not parseable
                                                     JSON. The HEX flags keep a
                                                     quote in an order number
                                                     from breaking out of the
                                                     attribute. --}}
                                            <button type="button" data-action="openVerifyModal"
                                                    data-args='{{ json_encode([$order->getKey(), $order->order_number], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}'
                                                    class="text-[var(--color-ink-soft)] hover:text-[var(--color-red)] text-sm p-1.5 rounded hover:bg-[var(--color-paper-dim)]"
                                                    title="Verify order">
                                                <i class="fa-solid fa-circle-check"></i>
                                            </button>
                                        @endunless

                                        @if($advanceTo)
                                            <form method="POST" action="{{ route('admin.orders.status', $order->getKey()) }}" class="inline" data-confirm="Move order #{{ $order->order_number }} to {{ $statusLabel($advanceTo) }}?">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="status" value="{{ $advanceTo }}">
                                                <button type="submit" class="text-[var(--color-ink-soft)] hover:text-[var(--color-red)] text-sm p-1.5 rounded hover:bg-[var(--color-paper-dim)]" title="Move to {{ $statusLabel($advanceTo) }}">
                                                    <i class="fa-solid fa-arrow-right"></i>
                                                </button>
                                            </form>
                                        @endif

                                        @unless($isTerminal)
                                            <form method="POST" action="{{ route('admin.orders.status', $order->getKey()) }}" class="inline" data-confirm="Cancel order #{{ $order->order_number }}? This cannot be reversed from here.">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="status" value="cancelled">
                                                <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm p-1.5 rounded hover:bg-[var(--color-red)]/5" title="Cancel order"><i class="fa-solid fa-ban"></i></button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                        <i class="fa-solid fa-inbox text-[var(--color-steel)]"></i>
                                    </div>
                                    <p class="text-sm font-medium text-[var(--color-ink)]">No {{ $statuses[$status]['label'] ?? '' }} orders</p>
                                    <p class="text-sm text-[var(--color-steel)] mt-1">
                                        @if($search)
                                            Nothing matches &ldquo;{{ $search }}&rdquo; in this stage.
                                            <a href="{{ route('admin.order-processor.index', ['status' => $status]) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Clear the search</a>.
                                        @else
                                            Nothing is waiting in this stage.
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
                <div class="p-4 border-t hairline">{{ $orders->links() }}</div>
            @endif
        </div>
    </form>
</div>

@include('components.admin.verify-order-modal')
@endsection

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('select-all');
    const checkboxes = Array.from(document.querySelectorAll('.order-checkbox'));
    const selectedCount = document.getElementById('selected-count');
    const bulkSubmit = document.getElementById('bulk-submit');
    const bulkStatus = document.querySelector('[data-bulk-status]');

    if (!selectAll || !bulkSubmit) {
        return;
    }

    function updateCount() {
        const count = checkboxes.filter(cb => cb.checked).length;

        selectedCount.textContent = count === 0
            ? 'Nothing selected'
            : count + ' order' + (count === 1 ? '' : 's') + ' selected';

        // Both a selection and a target are needed; enabling Apply with only
        // one of them produced a validation bounce.
        bulkSubmit.disabled = count === 0 || !bulkStatus.value;

        // Keep the header checkbox in step when rows are cleared by hand, so
        // it never claims everything is selected when it is not.
        const allChecked = count === checkboxes.length && count > 0;
        selectAll.checked = allChecked;
        selectAll.indeterminate = count > 0 && !allChecked;
    }

    selectAll.addEventListener('change', () => {
        checkboxes.forEach(cb => { cb.checked = selectAll.checked; });
        updateCount();
    });

    checkboxes.forEach(cb => cb.addEventListener('change', updateCount));
    bulkStatus.addEventListener('change', updateCount);

    updateCount();
});
</script>
@endpush