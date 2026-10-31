@extends('layouts.admin')

@section('title', 'Product Performance Analytics')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3 mb-8">
    <div>
        <h1 class="font-display text-3xl font-bold text-[var(--color-ink)]">Product Performance</h1>
        <p class="text-[var(--color-steel)] mt-1">Best-selling and underperforming products</p>
    </div>
    <a href="{{ route('admin.analytics.export', ['report_type' => 'products']) }}"
       class="px-4 py-2 bg-[var(--color-red)] text-white rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition-colors inline-flex items-center">
        <i class="fa-solid fa-download mr-2"></i>Export
    </a>
</div>
@endsection

@section('content')
@include('admin.analytics._nav')

<div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
        <div>
            <label for="range" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-2">Range</label>
            <select id="range" name="range" class="w-full border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="7" @selected($range == 7)>Last 7 days</option>
                <option value="30" @selected($range == 30)>Last 30 days</option>
                <option value="90" @selected($range == 90)>Last 90 days</option>
                <option value="365" @selected($range == 365)>Last 365 days</option>
            </select>
        </div>

        <div>
            <label for="sort_by" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-2">Sort By</label>
            <select id="sort_by" name="sort_by" class="w-full border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="revenue" @selected($sortBy === 'revenue')>Revenue</option>
                <option value="quantity" @selected($sortBy === 'quantity')>Quantity</option>
                <option value="orders" @selected($sortBy === 'orders')>Orders</option>
                <option value="name" @selected($sortBy === 'name')>Name</option>
            </select>
        </div>

        <div class="sm:col-span-2">
            <label for="category_id" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-2">Category</label>
            <select id="category_id" name="category_id" class="w-full border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->getKey() }}" @selected(($filters['category_id'] ?? null) === $category->getKey())>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="brand_id" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-2">Brand</label>
            <select id="brand_id" name="brand_id" class="w-full border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Brands</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->getKey() }}" @selected(($filters['brand_id'] ?? null) === $brand->getKey())>{{ $brand->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-2">Status</label>
            <select id="status" name="status" class="w-full border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All</option>
                <option value="active" @selected(($filters['status'] ?? null) === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Inactive</option>
            </select>
        </div>

        <div class="sm:col-span-2 lg:col-span-6 flex flex-wrap gap-3 pt-1">
            <button type="submit" class="px-5 py-2 bg-[var(--color-red)] text-white rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition-colors inline-flex items-center">
                <i class="fa-solid fa-filter mr-2"></i>Apply
            </button>
            @if(array_filter($filters ?? []))
                <a href="{{ route('admin.analytics.products', ['range' => $range]) }}" class="px-4 py-2 border hairline rounded-lg text-sm font-medium text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)] transition-colors inline-flex items-center">
                    <i class="fa-solid fa-xmark mr-2"></i>Clear
                </a>
            @endif
            <p class="text-sm text-[var(--color-steel)] self-center ml-auto">
                Showing {{ count($products) }} of {{ number_format($summary['total_products']) }} products
            </p>
        </div>
    </form>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @php
        $productStats = [
            ['label' => 'Total Products', 'value' => number_format($summary['total_products']), 'icon' => 'fa-boxes-stacked'],
            ['label' => 'Active Products', 'value' => number_format($summary['active_products']), 'icon' => 'fa-circle-check'],
            ['label' => 'Products With Sales', 'value' => number_format($summary['products_with_sales']), 'icon' => 'fa-cart-shopping'],
            ['label' => 'Out of Stock', 'value' => number_format($summary['out_of_stock']), 'icon' => 'fa-circle-xmark', 'alert' => $summary['out_of_stock'] > 0],
        ];
    @endphp
    @foreach($productStats as $stat)
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-[var(--color-steel)]">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-bold text-[var(--color-ink)] mt-1 {{ ($stat['alert'] ?? false) ? 'text-[var(--color-red)]' : '' }}">{{ $stat['value'] }}</p>
                </div>
                <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 {{ ($stat['alert'] ?? false) ? 'bg-[var(--color-red)]/10' : 'bg-[var(--color-paper-dim)]' }}">
                    <i class="fa-solid {{ $stat['icon'] }} {{ ($stat['alert'] ?? false) ? 'text-[var(--color-red)]' : 'text-[var(--color-ink-soft)]' }}"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Brand</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Qty Sold</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Revenue</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Orders</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Conv. Rate</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Stock</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($products as $product)
                    @php
                        $stock = (int) ($product->stock_total ?? 0);
                        $reorder = (int) ($product->stock_reorder_level ?? 0);
                        // Threshold is the product's own reorder level when it
                        // has one, else the app-wide low-stock setting, else
                        // "low only at zero". `(int) config('…', 5)` was 0 while
                        // the setting is blank, so every stocked product read as
                        // low. The old expression before that compared stock
                        // against 20% of itself, which is true for every value
                        // including zero.
                        $lowAt = $reorder > 0 ? $reorder : (low_stock_threshold() ?? 0);
                        $stockState = $stock <= 0 ? 'out' : ($stock <= $lowAt ? 'low' : 'ok');
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors">
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-3">
                                @if($product->image_path)
                                    <img src="{{ asset('storage/' . $product->image_path) }}" alt="" class="w-10 h-10 rounded object-cover flex-shrink-0">
                                @else
                                    <div class="w-10 h-10 rounded bg-[var(--color-paper-dim)] flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid fa-box text-[var(--color-steel)]"></i>
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <span class="font-medium text-[var(--color-ink)] block truncate">{{ $product->name }}</span>
                                    <p class="text-xs text-[var(--color-steel)] font-mono">{{ $product->sku }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-3 text-[var(--color-steel)]">{{ $product->category_names ?: '—' }}</td>
                        <td class="px-6 py-3 text-[var(--color-steel)]">{{ $product->brand_names ?: '—' }}</td>
                        <td class="px-6 py-3 text-right text-[var(--color-ink-soft)]">{{ number_format($product->total_sold) }}</td>
                        <td class="px-6 py-3 text-right font-medium text-[var(--color-ink)]">{{ format_currency((float) $product->total_revenue) }}</td>
                        <td class="px-6 py-3 text-right text-[var(--color-ink-soft)]">{{ number_format($product->order_count) }}</td>
                        <td class="px-6 py-3 text-right text-[var(--color-ink-soft)]">{{ $product->conversion_rate }}%</td>
                        <td class="px-6 py-3 text-right">
                            @if($stockState === 'out')
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-[var(--color-red)]/10 text-[var(--color-red)]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[var(--color-red)]"></span>Out
                                </span>
                            @elseif($stockState === 'low')
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-[#F59E0B]/10 text-[#B45309]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#F59E0B]"></span>{{ $stock }} low
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-[#1B7A43]/10 text-[#1B7A43]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#1B7A43]"></span>{{ $stock }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-solid fa-box-open text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">No products match these filters</p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">
                                @if(array_filter($filters ?? []))
                                    <a href="{{ route('admin.analytics.products') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Clear the filters</a> to see the full catalogue.
                                @else
                                    No products exist yet.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection