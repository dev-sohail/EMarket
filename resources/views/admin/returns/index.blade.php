@extends('layouts.admin')

@section('title', 'Returns')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Returns</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Customer return requests and their progress through review.</p>
    </div>
</div>
@endsection

@section('content')
@php
    // The old map keyed on 'pending', which ReturnStatus does not define, so
    // every row fell through to the fallback and all badges looked alike.
    $statusStyles = [
        'requested' => ['bg' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]', 'dot' => 'bg-[var(--color-steel)]', 'icon' => 'fa-clock'],
        'under_review' => ['bg' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]', 'dot' => 'bg-[var(--color-red)]', 'icon' => 'fa-magnifying-glass'],
        'approved' => ['bg' => 'bg-[#1B7A43]/10 text-[#1B7A43]', 'dot' => 'bg-[#1B7A43]', 'icon' => 'fa-circle-check'],
        'rejected' => ['bg' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]', 'dot' => 'bg-[var(--color-red)]', 'icon' => 'fa-circle-xmark'],
        'returned' => ['bg' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]', 'dot' => 'bg-[var(--color-steel)]', 'icon' => 'fa-box-open'],
        'completed' => ['bg' => 'bg-[#1B7A43]/10 text-[#1B7A43]', 'dot' => 'bg-[#1B7A43]', 'icon' => 'fa-box-open'],
    ];
    $openStatuses = ['requested', 'under_review'];
@endphp

{{-- Status tabs double as the filter; each carries its own count. --}}
<div class="flex flex-wrap gap-2 mb-6">
    <a href="{{ route('admin.returns.index', array_filter(['q' => $search ?? null])) }}"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border hairline text-sm transition {{ $status === null ? 'bg-[var(--color-ink)] text-white border-[var(--color-ink)]' : 'bg-[var(--color-paper)] text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)]' }}">
        All
        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-xs font-semibold {{ $status === null ? 'bg-white/20 text-white' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' }}">{{ $totalCount ?? 0 }}</span>
    </a>
    @foreach(\App\Enums\ReturnStatus::cases() as $case)
        @php
            $isActive = $status === $case;
            // Zero-count tabs are dimmed so the queue with work in it stands out.
            $count = $statusCounts[$case->value] ?? 0;
            $countStyle = $isActive
                ? 'bg-white/20 text-white'
                : ($count > 0 ? 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' : 'bg-transparent text-[var(--color-steel)]/60');
        @endphp
        <a href="{{ route('admin.returns.index', array_filter(['status' => $case->value, 'q' => $search ?? null])) }}"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border hairline text-sm transition {{ $isActive ? 'bg-[var(--color-ink)] text-white border-[var(--color-ink)]' : 'bg-[var(--color-paper)] text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)]' }}">
            {{ $case->label() }}
            <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-xs font-semibold {{ $countStyle }}">{{ $count }}</span>
        </a>
    @endforeach
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <form method="GET" action="{{ route('admin.returns.index') }}" class="p-4 border-b hairline flex flex-wrap items-center gap-3">
        @if($status)
            <input type="hidden" name="status" value="{{ $status->value }}">
        @endif
        <div class="relative flex-1 min-w-[240px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-steel)] text-sm"></i>
            <input type="search" name="q" value="{{ $search ?? '' }}" placeholder="Search by return #, order #, or customer email…" class="w-full border hairline rounded-lg pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
        </div>
        <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">Search</button>
        @if(($search ?? '') !== '' || $status !== null)
            <a href="{{ route('admin.returns.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Clear</a>
        @endif
    </form>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Return #</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Order</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Customer</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Items</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Requested</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($returns ?? [] as $return)
                    @php
                        $style = $statusStyles[$return->status?->value] ?? ['bg' => 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]', 'dot' => 'bg-[var(--color-steel)]', 'icon' => 'fa-circle-question'];
                        $needsAction = in_array($return->status?->value, $openStatuses, true);
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors {{ $needsAction ? 'border-l-2 border-l-[var(--color-red)]' : '' }}">
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.returns.show', $return->getKey()) }}" class="text-sm font-medium font-mono text-[var(--color-ink)] hover:text-[var(--color-red)]">{{ $return->return_number }}</a>
                            @if($return->admin_note)
                                <p class="text-xs text-[var(--color-steel)] mt-0.5 flex items-center gap-1"><i class="fa-solid fa-note-sticky text-[10px]"></i> Has admin note</p>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($return->order)
                                <a href="{{ route('admin.orders.show', $return->order->getKey()) }}" class="text-sm font-mono text-[var(--color-red)] hover:text-[var(--color-red-dark)]">#{{ $return->order->order_number }}</a>
                            @else
                                <span class="text-sm text-[var(--color-steel)]">&mdash;</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($return->customer)
                                <p class="text-sm font-medium text-[var(--color-ink)]">{{ $return->customer->full_name }}</p>
                                <p class="text-xs text-[var(--color-steel)]">{{ $return->customer->email }}</p>
                            @else
                                <span class="text-sm text-[var(--color-steel)]">&mdash;</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $return->items_count ?? 0 }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full {{ $style['bg'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $style['dot'] }}"></span>
                                {{ $return->status?->label() ?? 'Unknown' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)] whitespace-nowrap">{{ $return->created_at?->format('M d, Y') ?? '—' }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($needsAction)
                                    <form method="POST" action="{{ route('admin.returns.approve', $return->getKey()) }}" data-confirm="Approve return {{ $return->return_number }}?">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-[#1B7A43] text-white hover:bg-[#166436] transition">Approve</button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.returns.show', $return->getKey()) }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">{{ $needsAction ? 'Review' : 'View' }}</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-solid fa-rotate-left text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">
                                {{ ($search ?? '') !== '' || $status !== null ? 'No returns match this view' : 'No returns yet' }}
                            </p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">
                                @if(($search ?? '') !== '' || $status !== null)
                                    <a href="{{ route('admin.returns.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Clear the filters</a> to see every return.
                                @else
                                    Customer return requests will appear here as they are submitted.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(isset($returns) && method_exists($returns, 'links'))
        <div class="p-4 border-t hairline">{{ $returns->links() }}</div>
    @endif
</div>
@endsection