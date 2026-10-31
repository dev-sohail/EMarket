@extends('layouts.admin')

@section('title', 'Reviews')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Reviews</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">{{ $stats['total'] }} review{{ (int) $stats['total'] === 1 ? '' : 's' }} total — {{ (int) $stats['pending'] }} pending approval</p>
    </div>
    <form method="POST" action="{{ route('admin.reviews.toggleFront') }}" class="flex items-center gap-3">
        @csrf
        <span class="text-sm text-[var(--color-steel)]">Show Reviews on Front</span>
        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium transition {{ setting('reviews_enabled', true) ? 'bg-[var(--color-ink)] text-white' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] border hairline' }}">
            {{ setting('reviews_enabled', true) ? 'Enabled' : 'Disabled' }}
        </button>
    </form>
</div>
@endsection

@section('content')
@php
    $statusStyles = [
        \App\Enums\ReviewStatus::Pending->value => ['bg' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]', 'dot' => 'bg-[var(--color-steel)]', 'icon' => 'fa-clock'],
        \App\Enums\ReviewStatus::Approved->value => ['bg' => 'bg-[#1B7A43]/10 text-[#1B7A43]', 'dot' => 'bg-[#1B7A43]', 'icon' => 'fa-circle-check'],
        \App\Enums\ReviewStatus::Rejected->value => ['bg' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]', 'dot' => 'bg-[var(--color-red)]', 'icon' => 'fa-circle-xmark'],
    ];

    $ratingColor = fn (int $r) => $r >= 4 ? 'text-[#1B7A43]' : ($r >= 2 ? 'text-[#F59E0B]' : 'text-[var(--color-red)]');
@endphp

{{-- Summary cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    @php
        $cards = [
            ['label' => 'Pending', 'value' => number_format($stats['pending'] ?? 0), 'icon' => 'fa-clock', 'tone' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)'],
            ['label' => 'Approved', 'value' => number_format($stats['approved'] ?? 0), 'icon' => 'fa-circle-check', 'tone' => 'bg-[#1B7A43]/10 text-[#1B7A43]'],
            ['label' => 'Rejected', 'value' => number_format($stats['rejected'] ?? 0), 'icon' => 'fa-circle-xmark', 'tone' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]'],
            ['label' => 'Average Rating', 'value' => ($stats['average'] ?? 0) . '/5', 'icon' => 'fa-star', 'tone' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]'],
        ];
    @endphp
    @foreach($cards as $card)
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">{{ $card['label'] }}</p>
                    <p class="text-2xl font-bold text-[var(--color-ink)] mt-1 truncate">{{ $card['value'] }}</p>
                </div>
                <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 {{ $card['tone'] }}">
                    <i class="fa-solid {{ $card['icon'] }}"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Status tabs + search --}}
<div class="flex flex-wrap gap-2 mb-2">
    <a href="{{ route('admin.reviews.index', array_filter(['search' => $search])) }}"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border hairline text-sm font-medium transition {{ $status === null ? 'bg-[var(--color-ink)] text-white border-[var(--color-ink)]' : 'bg-[var(--color-paper)] text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)]' }}">
        All Reviews
        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-xs font-semibold {{ $status === null ? 'bg-white/20 text-white' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' }}">{{ number_format($stats['total'] ?? 0) }}</span>
    </a>
    @foreach(\App\Enums\ReviewStatus::cases() as $case)
        @php
            $count = ($stats[$case->value] ?? 0);
            $countStyle = $status === $case->value
                ? 'bg-white/20 text-white'
                : ($count > 0 ? 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' : 'bg-transparent text-[var(--color-steel)]/60');
        @endphp
        <a href="{{ route('admin.reviews.index', array_filter(['status' => $case->value, 'search' => $search])) }}"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border hairline text-sm font-medium transition {{ $status === $case->value ? 'bg-[var(--color-ink)] text-white border-[var(--color-ink)]' : 'bg-[var(--color-paper)] text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)]' }}">
            {{ $case->label() }}
            <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-xs font-semibold {{ $countStyle }}">{{ number_format($count) }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.reviews.index') }}" class="bg-[var(--color-paper)] rounded-lg border hairline p-4 mb-4">
    @if($status)
        <input type="hidden" name="status" value="{{ $status }}">
    @endif
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[240px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-steel)] text-sm"></i>
            <input type="search" name="search" value="{{ $search }}" placeholder="Search by title, comment, product, or customer…" class="w-full border hairline rounded-lg pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
        </div>
        <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">Search</button>
        @if($search)
            <a href="{{ route('admin.reviews.index', array_filter(['status' => $status])) }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Clear</a>
        @endif
    </div>
</form>

{{-- Reviews table --}}
<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Review</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Product</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Customer</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Rating</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Date</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($reviews ?? [] as $review)
                    @php
                        $style = $statusStyles[$review->status?->value ?? 'pending'] ?? $statusStyles['pending'];
                        $needsAction = in_array($review->status, [\App\Enums\ReviewStatus::Pending, \App\Enums\ReviewStatus::Rejected], true);
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors {{ $needsAction ? 'border-l-2 border-l-[var(--color-red)]' : '' }}">
                        <td class="px-6 py-4 max-w-sm">
                            <div class="flex items-start gap-2 mb-2">
                                <div class="flex{{ $review->rating > 0 ? ' ' . $ratingColor($review->rating) : '' }}">
                                    @for($i = 1; $i <= 5; $i++)
                                        @if($i <= $review->rating)
                                            <i class="fa-solid fa-star text-xs"></i>
                                        @else
                                            <i class="fa-regular fa-star text-xs text-[var(--color-line)]"></i>
                                        @endif
                                    @endfor
                                </div>
                            </div>
                            @if($review->title)
                                <p class="font-medium text-sm text-[var(--color-ink)] mb-1">{{ $review->title }}</p>
                            @endif
                            @if($review->comment)
                                <p class="text-sm text-[var(--color-steel)] line-clamp-2">{{ Str::limit($review->comment, 120) }}</p>
                            @endif
                            @if($review->images && $review->images->isNotEmpty())
                                <div class="mt-2 flex items-center gap-1">
                                    @foreach($review->images as $image)
                                        <img src="{{ asset('storage/'.$image->path) }}" alt="Review image" class="w-8 h-8 rounded border hairline object-cover">
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                @if($review->product && $review->product->getPrimaryImageAttribute())
                                    <img src="{{ asset('storage/'.$review->product->getPrimaryImageAttribute()) }}" alt="{{ $review->product->name }}" class="w-10 h-10 rounded border hairline object-cover flex-shrink-0">
                                @else
                                    <div class="w-10 h-10 rounded border hairline flex-shrink-0 flex items-center justify-center bg-[var(--color-paper-dim)]">
                                        <i class="fa-solid fa-image text-[var(--color-steel)]"></i>
                                    </div>
                                @endif
                                <a href="{{ $review->product ? route('admin.products.edit', $review->product->getKey()) : '#' }}" class="text-sm font-medium text-[var(--color-ink)] hover:text-[var(--color-red)] truncate">
                                    {{ $review->product->name ?? '-' }}
                                </a>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-[var(--color-ink)]">{{ $review->customer->full_name ?? 'Unknown' }}</p>
                            <p class="text-xs text-[var(--color-steel)]">{{ $review->customer->email ?? '-' }}</p>
                            @if($review->is_verified_purchase)
                                <span class="mt-1 inline-flex items-center gap-1 text-xs text-[#1B7A43]"><i class="fa-solid fa-check-circle"></i> Verified purchase</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-2xl font-bold text-[var(--color-ink)]">{{ $review->rating }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full {{ $style['bg'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $style['dot'] }}"></span>
                                {{ $review->status?->label() ?? 'Unknown' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)] whitespace-nowrap">
                            <span class="block">{{ $review->created_at->format('M d, Y') }}</span>
                            <span class="block text-xs">{{ $review->created_at->format('H:i') }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($review->status !== \App\Enums\ReviewStatus::Approved)
                                    <form method="POST" action="{{ route('admin.reviews.approve', $review->getKey()) }}" data-confirm="Approve this review?">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-[#1B7A43] text-white hover:bg-[#166436] transition">Approve</button>
                                    </form>
                                @endif
                                @if($review->status !== \App\Enums\ReviewStatus::Rejected)
                                    <form method="POST" action="{{ route('admin.reviews.reject', $review->getKey()) }}" data-confirm="Reject this review?">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-[var(--color-red)] text-white hover:bg-[var(--color-red-dark)] transition">Reject</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.reviews.destroy', $review->getKey()) }}" data-confirm="Delete this review? This cannot be undone.">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Delete" class="p-1.5 rounded-lg text-[var(--color-red)] hover:bg-[var(--color-red)]/10 transition">
                                        <i class="fa-solid fa-trash-can text-sm"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-solid fa-star text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">
                                {{ ($search ?? '') !== '' || $status !== null ? 'No reviews match this view' : 'No reviews yet' }}
                            </p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">
                                @if(($search ?? '') !== '' || $status !== null)
                                    <a href="{{ route('admin.reviews.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Clear the filters</a> to see all reviews.
                                @else
                                    Reviews will appear here once customers submit them.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($reviews, 'links'))
        <div class="p-4 border-t hairline">{{ $reviews->links() }}</div>
    @endif
</div>
@endsection
