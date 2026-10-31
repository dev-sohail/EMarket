@extends('layouts.admin')

@section('title', 'Flash Sales')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Flash Sales</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Time-boxed discounts, their schedule, and live status.</p>
    </div>
    <a href="{{ route('admin.flash-sales.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2">
        <i class="fa-solid fa-plus"></i>
        Add Flash Sale
    </a>
</div>
@endsection

@section('content')
@php
    $saleStats = [
        ['label' => 'Total Sales', 'value' => $stats['total'] ?? 0, 'icon' => 'fa-bolt', 'tone' => 'neutral', 'query' => []],
        ['label' => 'Live Now', 'value' => $stats['live'] ?? 0, 'icon' => 'fa-circle-play', 'tone' => 'success', 'query' => ['state' => 'live']],
        ['label' => 'Scheduled', 'value' => $stats['scheduled'] ?? 0, 'icon' => 'fa-clock', 'tone' => 'warning', 'query' => ['state' => 'scheduled']],
        ['label' => 'Ended', 'value' => $stats['ended'] ?? 0, 'icon' => 'fa-flag-checkered', 'tone' => 'danger', 'query' => ['state' => 'ended']],
    ];
    $activeFilterCount = count($filters ?? []);
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    @foreach($saleStats as $stat)
        @php
            $toneClasses = match ($stat['tone']) {
                'success' => ['value' => 'text-[#1B7A43]', 'icon' => 'bg-[#1B7A43]/10 text-[#1B7A43]'],
                'danger' => ['value' => 'text-[var(--color-red)]', 'icon' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]'],
                'warning' => ['value' => 'text-[#B45309]', 'icon' => 'bg-[#F59E0B]/10 text-[#B45309]'],
                default => ['value' => 'text-[var(--color-ink)]', 'icon' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]'],
            };
            $query = array_merge(['search' => $filters['search'] ?? null, 'status' => $filters['status'] ?? null], $stat['query']);
        @endphp
        <a href="{{ route('admin.flash-sales.index', array_filter($query, fn ($v) => $v !== null && $v !== '')) }}" class="block bg-[var(--color-paper)] rounded-lg border hairline p-5 hover:border-[var(--color-steel)] transition">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-bold {{ $toneClasses['value'] }} mt-1">{{ number_format($stat['value']) }}</p>
                </div>
                <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 {{ $toneClasses['icon'] }}">
                    <i class="fa-solid {{ $stat['icon'] }}"></i>
                </div>
            </div>
        </a>
    @endforeach
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 mb-6">
    <form method="GET" class="space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[220px]">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-steel)] text-sm"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search flash sales by name…" class="w-full border hairline rounded-lg pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
            </div>

            <select name="state" data-auto-submit aria-label="Schedule state" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Schedules</option>
                <option value="live" @selected(($filters['state'] ?? null) === 'live')>Live now</option>
                <option value="scheduled" @selected(($filters['state'] ?? null) === 'scheduled')>Scheduled</option>
                <option value="ended" @selected(($filters['state'] ?? null) === 'ended')>Ended</option>
            </select>

            <select name="status" data-auto-submit aria-label="Status" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Statuses</option>
                <option value="active" @selected(($filters['status'] ?? null) === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Inactive</option>
            </select>

            <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">Filter</button>

            @if($activeFilterCount > 0)
                <a href="{{ route('admin.flash-sales.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Clear</a>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-3 border-t hairline">
            <label for="sort_by" class="text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Sort</label>
            <select id="sort_by" name="sort_by" data-auto-submit class="border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                @foreach([
                    'starts_at' => 'Start date',
                    'ends_at' => 'End date',
                    'name' => 'Name',
                    'sale_price' => 'Flash price',
                    'created_at' => 'Created',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['sort_by'] ?? 'starts_at') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="sort_order" data-auto-submit aria-label="Sort direction" class="border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="desc" @selected(($filters['sort_order'] ?? 'desc') === 'desc')>Descending</option>
                <option value="asc" @selected(($filters['sort_order'] ?? 'desc') === 'asc')>Ascending</option>
            </select>

            <p class="text-sm text-[var(--color-steel)] ml-auto">
                {{ number_format($flashSales->total() ?? 0) }} flash sale{{ ($flashSales->total() ?? 0) === 1 ? '' : 's' }}
                @if($activeFilterCount > 0)
                    <span class="text-[var(--color-red)]">· {{ $activeFilterCount }} filter{{ $activeFilterCount === 1 ? '' : 's' }} applied</span>
                @endif
            </p>
        </div>
    </form>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[1080px]">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Flash Sale</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Flash Price</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Products</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Schedule</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($flashSales ?? [] as $flashSale)
                    @php
                        $now = now();
                        $startsAt = $flashSale->starts_at;
                        $endsAt = $flashSale->ends_at;

                        if (! $flashSale->is_active) {
                            $stateLabel = 'Inactive';
                            $stateClasses = 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]';
                            $stateDot = 'bg-[var(--color-steel)]';
                            $stateNote = 'Hidden from the storefront';
                        } elseif ($endsAt && $endsAt->lt($now)) {
                            $stateLabel = 'Ended';
                            $stateClasses = 'bg-[#1B7A43]/10 text-[#1B7A43]';
                            $stateDot = 'bg-[#1B7A43]';
                            $stateNote = 'Closed '.$endsAt->diffForHumans();
                        } elseif ($startsAt && $startsAt->gt($now)) {
                            $stateLabel = 'Scheduled';
                            $stateClasses = 'bg-[#F59E0B]/10 text-[#B45309]';
                            $stateDot = 'bg-[#F59E0B]';
                            $stateNote = 'Opens '.$startsAt->diffForHumans();
                        } else {
                            $stateLabel = 'Live';
                            $stateClasses = 'bg-[var(--color-red)]/10 text-[var(--color-red)]';
                            $stateDot = 'bg-[var(--color-red)]';
                            // ends_at is nullable in the schema, so a sale
                            // that is active with no end date reaches this
                            // branch with nothing to diff against.
                            $stateNote = $endsAt ? 'Closes in '.$endsAt->diffForHumans() : 'No end date set';
                        }

                        $progress = null;
                        if ($startsAt && $endsAt && $endsAt->gt($startsAt)) {
                            $total = $startsAt->diffInSeconds($endsAt);
                            $elapsed = max(0, $now->getTimestamp() - $startsAt->getTimestamp());
                            $progress = max(0, min(100, round(($elapsed / max(1, $total)) * 100)));
                        }

                        $productCount = $flashSale->products_count ?? $flashSale->products->count();
                        $discount = null;
                        $maxDiscount = 0.0;
                        foreach ($flashSale->products as $product) {
                            $base = (float) $product->base_price;
                            $flash = (float) ($product->pivot->flash_price ?? $flashSale->sale_price);
                            if ($base > 0 && $flash < $base) {
                                $maxDiscount = max($maxDiscount, (($base - $flash) / $base) * 100);
                            }
                        }
                        if ($maxDiscount > 0) {
                            $discount = round($maxDiscount);
                        }
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-[var(--color-red)]/10 text-[var(--color-red)] rounded-lg flex-shrink-0 flex items-center justify-center">
                                    <i class="fa-solid fa-bolt"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-[var(--color-ink)] text-sm truncate">{{ $flashSale->name }}</p>
                                    <p class="text-xs text-[var(--color-steel)] font-mono">{{ $flashSale->getKey() }}</p>
                                </div>
                                @if($discount)
                                    <span class="ml-1 inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-[var(--color-red)] text-white whitespace-nowrap">
                                        <i class="fa-solid fa-tag text-[10px]"></i>
                                        -{{ $discount }}%
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            @if($flashSale->sale_price)
                                <span class="font-medium text-[var(--color-red)]">{{ format_currency((float) $flashSale->sale_price) }}</span>
                                <p class="text-xs text-[var(--color-steel)]">default</p>
                            @else
                                <span class="text-[var(--color-steel)]">Per product</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if($productCount > 0)
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-[var(--color-ink)]">{{ number_format($productCount) }}</span>
                                    <div class="flex -space-x-2">
                                        @foreach($flashSale->products->take(3) as $product)
                                            <span class="w-6 h-6 rounded-full border-2 border-[var(--color-paper)] bg-[var(--color-paper-dim)] overflow-hidden flex items-center justify-center" title="{{ $product->name }}">
                                                @if($product->primary_image)
                                                    <img src="{{ asset('storage/' . $product->primary_image) }}" alt="" class="w-full h-full object-cover">
                                                @else
                                                    <i class="fa-solid fa-image text-[10px] text-[var(--color-steel)]"></i>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                    @if($productCount > 3)
                                        <span class="text-xs text-[var(--color-steel)]">+{{ $productCount - 3 }}</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-[var(--color-steel)]">None selected</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <p class="text-[var(--color-ink-soft)]">{{ $startsAt ? $startsAt->format('M d, Y H:i') : '—' }}</p>
                            <p class="text-xs text-[var(--color-steel)]">to {{ $endsAt ? $endsAt->format('M d, Y H:i') : '—' }}</p>
                            @if($progress !== null)
                                <div class="w-32 h-1 rounded-full bg-[var(--color-line)] mt-2 overflow-hidden" title="{{ $progress }}% of the window elapsed">
                                    <div class="h-full rounded-full {{ $stateLabel === 'Live' ? 'bg-[var(--color-red)]' : 'bg-[var(--color-steel)]' }}" style="width: {{ $progress }}%"></div>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full {{ $stateClasses }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $stateDot }}"></span>
                                {{ $stateLabel }}
                            </span>
                            <p class="text-xs text-[var(--color-steel)] mt-1">{{ $stateNote }}</p>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <form method="POST" action="{{ route('admin.flash-sales.toggleStatus', $flashSale->getKey()) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm" title="{{ $flashSale->is_active ? 'Click to deactivate' : 'Click to activate' }}">
                                        {{ $flashSale->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                                <a href="{{ route('admin.flash-sales.edit', $flashSale->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.flash-sales.destroy', $flashSale->getKey()) }}" data-confirm="Delete &quot;{{ $flashSale->name }}&quot;? This cannot be undone." class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-solid fa-bolt text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">No flash sales found</p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">
                                @if($activeFilterCount > 0)
                                    <a href="{{ route('admin.flash-sales.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Clear the filters</a> to see every flash sale.
                                @else
                                    <a href="{{ route('admin.flash-sales.create') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Create your first flash sale</a> to get started.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(isset($flashSales) && method_exists($flashSales, 'links'))
        <div class="p-4 border-t hairline">{{ $flashSales->links() }}</div>
    @endif
</div>
@endsection