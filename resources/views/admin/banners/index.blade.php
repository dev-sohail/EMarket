@extends('layouts.admin')

@section('title', 'Banners')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Banners</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Hero, promotional, sale and footer placements across the storefront.</p>
    </div>
    <a href="{{ route('admin.banners.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2">
        <i class="fa-solid fa-plus"></i>
        Add Banner
    </a>
</div>
@endsection

@section('content')
@php
    $bannerStats = [
        ['label' => 'Total Banners', 'value' => $stats['total'] ?? 0, 'icon' => 'fa-images', 'tone' => 'neutral', 'query' => []],
        ['label' => 'Live Now', 'value' => $stats['live'] ?? 0, 'icon' => 'fa-circle-play', 'tone' => 'success', 'query' => ['state' => 'live']],
        ['label' => 'Scheduled', 'value' => $stats['scheduled'] ?? 0, 'icon' => 'fa-clock', 'tone' => 'warning', 'query' => ['state' => 'scheduled']],
        ['label' => 'Expired', 'value' => $stats['expired'] ?? 0, 'icon' => 'fa-flag-checkered', 'tone' => 'danger', 'query' => ['state' => 'expired']],
    ];

    $positionMeta = [
        'homepage' => ['label' => 'Homepage', 'classes' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]', 'icon' => 'fa-house'],
        'promotional' => ['label' => 'Promotional', 'classes' => 'bg-[#1B7A43]/10 text-[#1B7A43]', 'icon' => 'fa-tags'],
        'sale' => ['label' => 'Sale', 'classes' => 'bg-[#F59E0B]/10 text-[#B45309]', 'icon' => 'fa-percent'],
        'footer' => ['label' => 'Footer', 'classes' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]', 'icon' => 'fa-shoe-prints'],
    ];

    $activeFilterCount = count($filters ?? []);
    $now = now();
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    @foreach($bannerStats as $stat)
        @php
            $toneClasses = match ($stat['tone']) {
                'success' => ['value' => 'text-[#1B7A43]', 'icon' => 'bg-[#1B7A43]/10 text-[#1B7A43]'],
                'danger' => ['value' => 'text-[var(--color-red)]', 'icon' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]'],
                'warning' => ['value' => 'text-[#B45309]', 'icon' => 'bg-[#F59E0B]/10 text-[#B45309]'],
                default => ['value' => 'text-[var(--color-ink)]', 'icon' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]'],
            };
            $query = array_merge([
                'search' => $filters['search'] ?? null,
                'position' => $filters['position'] ?? null,
                'status' => $filters['status'] ?? null,
            ], $stat['query']);
            $isActive = ($filters['state'] ?? null) === ($stat['query']['state'] ?? null);
        @endphp
        <a href="{{ route('admin.banners.index', array_filter($query, fn ($v) => $v !== null && $v !== '')) }}"
           class="block bg-[var(--color-paper)] rounded-lg border hairline p-5 transition {{ $isActive ? 'border-[var(--color-red)] shadow-[0_0_0_1px_var(--color-red)]' : 'hover:border-[var(--color-steel)]' }}">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">{{ $stat['label'] }}</p>
                    <p class="font-display text-2xl font-bold {{ $toneClasses['value'] }} mt-1">{{ number_format($stat['value']) }}</p>
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
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search banners by title, subtitle or CTA…" class="w-full border hairline rounded-lg pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
            </div>

            <select name="position" data-auto-submit aria-label="Position" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Positions</option>
                @foreach($positionMeta as $value => $meta)
                    <option value="{{ $value }}" @selected(($filters['position'] ?? null) === $value)>{{ $meta['label'] }}</option>
                @endforeach
            </select>

            <select name="status" data-auto-submit aria-label="Status" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Statuses</option>
                <option value="active" @selected(($filters['status'] ?? null) === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Inactive</option>
            </select>

            <select name="state" data-auto-submit aria-label="Schedule state" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Schedules</option>
                <option value="live" @selected(($filters['state'] ?? null) === 'live')>Live now</option>
                <option value="scheduled" @selected(($filters['state'] ?? null) === 'scheduled')>Scheduled</option>
                <option value="expired" @selected(($filters['state'] ?? null) === 'expired')>Expired</option>
            </select>

            <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">Filter</button>

            @if($activeFilterCount > 0)
                <a href="{{ route('admin.banners.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Clear</a>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-3 border-t hairline">
            <label for="sort_by" class="text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Sort</label>
            <select id="sort_by" name="sort_by" data-auto-submit class="border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                @foreach([
                    'sort_order' => 'Display order',
                    'title' => 'Title',
                    'position' => 'Position',
                    'starts_at' => 'Start date',
                    'ends_at' => 'End date',
                    'created_at' => 'Created',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['sort_by'] ?? 'sort_order') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="sort_order" data-auto-submit aria-label="Sort direction" class="border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="asc" @selected(($filters['sort_order'] ?? 'asc') === 'asc')>Ascending</option>
                <option value="desc" @selected(($filters['sort_order'] ?? 'asc') === 'desc')>Descending</option>
            </select>

            <p class="text-sm text-[var(--color-steel)] ml-auto">
                {{ number_format($banners->total() ?? 0) }} banner{{ ($banners->total() ?? 0) === 1 ? '' : 's' }}
                @if($activeFilterCount > 0)
                    <span class="text-[var(--color-red)]">· {{ $activeFilterCount }} filter{{ $activeFilterCount === 1 ? '' : 's' }} applied</span>
                @endif
            </p>
        </div>
    </form>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[980px]">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Preview</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Banner</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Position</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Links &amp; CTA</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Schedule</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($banners ?? [] as $banner)
                    @php
                        $meta = $positionMeta[$banner->position] ?? ['label' => ucfirst((string) $banner->position), 'classes' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]', 'icon' => 'fa-image'];
                        $startsAt = $banner->starts_at;
                        $endsAt = $banner->ends_at;

                        if (! $banner->is_active) {
                            $stateLabel = 'Inactive';
                            $stateClasses = 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]';
                            $stateDot = 'bg-[var(--color-steel)]';
                            $stateNote = 'Hidden from the storefront';
                        } elseif ($endsAt && $endsAt->lt($now)) {
                            $stateLabel = 'Expired';
                            $stateClasses = 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]';
                            $stateDot = 'bg-[var(--color-steel)]';
                            $stateNote = 'Closed '.$endsAt->diffForHumans();
                        } elseif ($startsAt && $startsAt->gt($now)) {
                            $stateLabel = 'Scheduled';
                            $stateClasses = 'bg-[#F59E0B]/10 text-[#B45309]';
                            $stateDot = 'bg-[#F59E0B]';
                            $stateNote = 'Opens '.$startsAt->diffForHumans();
                        } else {
                            $stateLabel = 'Live';
                            $stateClasses = 'bg-[#1B7A43]/10 text-[#1B7A43]';
                            $stateDot = 'bg-[#1B7A43]';
                            $stateNote = $endsAt ? 'Closes in '.$endsAt->diffForHumans() : 'Always on';
                        }

                        $progress = null;
                        if ($startsAt && $endsAt && $endsAt->gt($startsAt)) {
                            $total = $startsAt->diffInSeconds($endsAt);
                            $elapsed = max(0, $now->getTimestamp() - $startsAt->getTimestamp());
                            $progress = max(0, min(100, round(($elapsed / max(1, $total)) * 100)));
                        }

                        $secondaryCta = collect([
                            ['label' => $banner->cta_text, 'url' => $banner->url],
                            ['label' => $banner->cta_text_2, 'url' => $banner->url_2],
                        ])->filter(fn ($cta) => filled($cta['label']));
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors">
                        <td class="px-6 py-4">
                            @if($banner->image)
                                <a href="{{ route('admin.banners.edit', $banner->getKey()) }}" class="block w-24 h-14 rounded-md overflow-hidden bg-[var(--color-paper-dim)] border hairline flex-shrink-0" title="Edit {{ $banner->title }}">
                                    <img src="{{ asset('storage/' . $banner->image) }}" alt="{{ $banner->title }}" class="w-full h-full object-cover">
                                </a>
                            @elseif($banner->color)
                                <div class="w-24 h-14 rounded-md border hairline flex items-center justify-center" style="background-color: {{ $banner->color }}" title="{{ $banner->color }}">
                                    <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-black/30 text-white uppercase">{{ $banner->color }}</span>
                                </div>
                            @else
                                <div class="w-24 h-14 rounded-md border hairline bg-[var(--color-paper-dim)] flex items-center justify-center">
                                    <i class="fa-regular fa-image text-[var(--color-steel)]"></i>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="min-w-0 max-w-[280px]">
                                <p class="text-sm font-medium text-[var(--color-ink)] truncate">{{ $banner->title }}</p>
                                @if($banner->subtitle)
                                    <p class="text-xs text-[var(--color-steel)] truncate">{{ $banner->subtitle }}</p>
                                @endif
                                @if($banner->description)
                                    <p class="text-xs text-[var(--color-steel)] truncate mt-0.5" title="{{ $banner->description }}">{{ $banner->description }}</p>
                                @endif
                                <p class="text-[11px] text-[var(--color-steel)] font-mono mt-1">#{{ $banner->sort_order }} · {{ $banner->getKey() }}</p>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap {{ $meta['classes'] }}">
                                <i class="fa-solid {{ $meta['icon'] }} text-[10px]"></i>
                                {{ $meta['label'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if($secondaryCta->isNotEmpty())
                                <div class="space-y-1">
                                    @foreach($secondaryCta as $cta)
                                        <div class="flex items-center gap-2 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded border hairline text-[var(--color-ink-soft)]">{{ $cta['label'] }}</span>
                                            @if($cta['url'])
                                                <a href="{{ $cta['url'] }}" target="_blank" rel="noopener" class="text-xs text-[var(--color-steel)] hover:text-[var(--color-red)] truncate max-w-[140px]" title="{{ $cta['url'] }}">{{ $cta['url'] }}</a>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-[var(--color-steel)]">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <p class="text-[var(--color-ink-soft)]">{{ $startsAt ? $startsAt->format('M d, Y H:i') : '—' }}</p>
                            <p class="text-xs text-[var(--color-steel)]">to {{ $endsAt ? $endsAt->format('M d, Y H:i') : '—' }}</p>
                            @if($progress !== null)
                                <div class="w-32 h-1 rounded-full bg-[var(--color-line)] mt-2 overflow-hidden" title="{{ $progress }}% of the window elapsed">
                                    <div class="h-full rounded-full {{ $stateLabel === 'Live' ? 'bg-[#1B7A43]' : 'bg-[var(--color-steel)]' }}" style="width: {{ $progress }}%"></div>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap {{ $stateClasses }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $stateDot }}"></span>
                                {{ $stateLabel }}
                            </span>
                            <p class="text-xs text-[var(--color-steel)] mt-1 whitespace-nowrap">{{ $stateNote }}</p>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3 whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.banners.toggleStatus', $banner->getKey()) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm" title="{{ $banner->is_active ? 'Click to hide this banner' : 'Click to show this banner' }}">
                                        {{ $banner->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                                <a href="{{ route('admin.banners.edit', $banner->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.banners.destroy', $banner->getKey()) }}" data-confirm="Delete &quot;{{ $banner->title }}&quot;? This cannot be undone." class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-solid fa-images text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">No banners found</p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">
                                @if($activeFilterCount > 0)
                                    <a href="{{ route('admin.banners.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Clear the filters</a> to see every banner.
                                @else
                                    <a href="{{ route('admin.banners.create') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Create your first banner</a> to get started.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($banners, 'links'))
        <div class="p-4 border-t hairline">{{ $banners->links() }}</div>
    @endif
</div>
@endsection
