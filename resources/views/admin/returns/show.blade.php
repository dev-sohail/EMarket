@extends('layouts.admin')
@section('title', 'Return #' . ($return->return_number ?? ''))
@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Return #{{ $return->return_number ?? '' }}</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Order #{{ $return->order->order_number ?? 'N/A' }} &bull; {{ $return->created_at?->format('M d, Y H:i') }}</p>
    </div>
    <a href="{{ route('admin.returns.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Returns</a>
</div>
@endsection
@section('content')
@php
    $statusConfig = [
        'requested' => ['bg' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]', 'label' => 'Requested'],
        'under_review' => ['bg' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]', 'label' => 'Under Review'],
        'approved' => ['bg' => 'bg-[#1B7A43]/10 text-[#1B7A43]', 'label' => 'Approved'],
        'rejected' => ['bg' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]', 'label' => 'Rejected'],
        'returned' => ['bg' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]', 'label' => 'Returned'],
        'completed' => ['bg' => 'bg-[#1B7A43]/10 text-[#1B7A43]', 'label' => 'Completed'],
    ];
    $cfg = $statusConfig[$return->status?->value] ?? ['bg' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]', 'label' => $return->status?->label() ?? 'Unknown'];
@endphp
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-[var(--color-ink)]">Return Details</h2>
                <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full {{ $cfg['bg'] }}">{{ $cfg['label'] }}</span>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div><dt class="text-[var(--color-steel)]">Return #</dt><dd class="font-medium">{{ $return->return_number }}</dd></div>
                <div><dt class="text-[var(--color-steel)]">Order</dt><dd class="font-medium"><a href="{{ route('admin.orders.show', $return->order) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)]">#{{ $return->order->order_number ?? '-' }}</a></dd></div>
                <div><dt class="text-[var(--color-steel)]">Customer</dt><dd class="font-medium">{{ $return->customer->full_name ?? '-' }}</dd></div>
                <div><dt class="text-[var(--color-steel)]">Date</dt><dd>{{ $return->created_at?->format('M d, Y H:i') }}</dd></div>

                <div><dt class="text-[var(--color-steel)]">Status</dt><dd><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $cfg['bg'] }}">{{ $cfg['label'] }}</span></dd></div>
                <div class="sm:col-span-2"><dt class="text-[var(--color-steel)]">Description</dt><dd>{{ $return->description ?: 'No description provided' }}</dd></div>
                @if($return->admin_note)
                    <div class="sm:col-span-2"><dt class="text-[var(--color-steel)]">Admin Note</dt><dd class="text-[var(--color-red-dark)]">{{ $return->admin_note }}</dd></div>
                @endif
            </dl>
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-4">Return Items</h2>
            <div class="space-y-3">
                @forelse($return->items ?? [] as $item)
                    <div class="flex items-center justify-between text-sm border-b last:border-0 pb-3 last:pb-0">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-[var(--color-paper-dim)] rounded flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-box text-[var(--color-steel)]"></i>
                            </div>
                            <div>
                                <p class="font-medium text-[var(--color-ink)]">{{ $item->orderItem->product->name ?? 'Product' }}</p>
                                <p class="text-xs text-[var(--color-steel)]">Qty: {{ $item->quantity }} &bull; SKU: {{ $item->orderItem->sku ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-center text-[var(--color-steel)] text-sm py-4">No items.</p>
                @endforelse
            </div>
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-4">Status History</h2>
            <div class="space-y-4">
                @forelse($return->statusHistories as $history)
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-check text-xs text-[var(--color-red)]"></i></div>
                        <div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">
                                @if($history->from_status)
                                    {{ $history->from_status->label() }} &rarr; {{ $history->to_status?->label() ?? 'Unknown' }}
                                @else
                                    {{ $history->to_status?->label() ?? 'Unknown' }}
                                @endif
                            </p>
                            @if($history->note)<p class="text-sm text-[var(--color-steel)] mt-0.5">{{ $history->note }}</p>@endif
                            <p class="text-xs text-[var(--color-steel)] mt-0.5">{{ $history->created_at?->format('M d, Y H:i') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-[var(--color-steel)]">No status updates yet.</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-3">Customer</h2>
            <div class="text-sm text-[var(--color-steel)] space-y-1">
                <p class="font-medium">{{ $return->customer->full_name ?? '-' }}</p>
                <p>{{ $return->customer->email ?? '' }}</p>
                <p>{{ $return->customer->phone ?? '' }}</p>
            </div>
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-3">Admin Note</h2>
            <form method="POST" action="{{ route('admin.returns.addNote', $return) }}">
                @csrf
                <textarea name="admin_note" rows="3" class="w-full border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]" placeholder="Add a note...">{{ $return->admin_note }}</textarea>
                <button type="submit" class="mt-2 bg-[var(--color-ink)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)]">Save Note</button>
            </form>
        </div>
        @if(in_array($return->status?->value, ['requested', 'under_review'], true))
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-3">Actions</h2>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('admin.returns.approve', $return) }}">
                        @csrf
                        <button type="submit" class="bg-[#1B7A43] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#166436]">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('admin.returns.reject', $return) }}">
                        @csrf
                        <button type="submit" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)]">Reject</button>
                    </form>
                </div>
            </div>
        @endif
        @if($return->status?->value === 'approved')
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-3">Actions</h2>
                <form method="POST" action="{{ route('admin.returns.complete', $return) }}">
                    @csrf
                    <button type="submit" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)]">Mark Complete</button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
