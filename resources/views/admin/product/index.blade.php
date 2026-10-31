@extends('layouts.admin')

@section('title', 'Products')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Products</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Catalogue, pricing, and stock levels.</p>
    </div>
    <a href="{{ route('admin.products.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2">
        <i class="fa-solid fa-plus"></i>
        Add Product
    </a>
</div>
@endsection

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    @php
        $productStats = [
            ['label' => 'Total Products', 'value' => $stats['total'] ?? 0, 'icon' => 'fa-boxes-stacked', 'tone' => 'neutral', 'query' => []],
            ['label' => 'Active', 'value' => $stats['active'] ?? 0, 'icon' => 'fa-circle-check', 'tone' => 'success', 'query' => ['status' => 'active']],
            ['label' => 'Out of Stock', 'value' => $stats['out_of_stock'] ?? 0, 'icon' => 'fa-circle-xmark', 'tone' => 'danger', 'query' => ['stock_status' => 'out_of_stock']],
            ['label' => 'Featured', 'value' => $stats['featured'] ?? 0, 'icon' => 'fa-star', 'tone' => 'warning', 'query' => []],
        ];
        $activeFilterCount = count(array_filter($filters ?? []));
    @endphp
    @foreach($productStats as $stat)
        @php
            $toneClasses = match ($stat['tone']) {
                'success' => ['value' => 'text-[#1B7A43]', 'icon' => 'bg-[#1B7A43]/10 text-[#1B7A43]'],
                'danger' => ['value' => 'text-[var(--color-red)]', 'icon' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]'],
                'warning' => ['value' => 'text-[#B45309]', 'icon' => 'bg-[#F59E0B]/10 text-[#B45309]'],
                default => ['value' => 'text-[var(--color-ink)]', 'icon' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]'],
            };
        @endphp
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-bold {{ $toneClasses['value'] }} mt-1">{{ number_format($stat['value']) }}</p>
                </div>
                <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 {{ $toneClasses['icon'] }}">
                    <i class="fa-solid {{ $stat['icon'] }}"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 mb-6">
    <form method="GET" class="space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[220px]">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-steel)] text-sm"></i>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name, SKU, or description…" class="w-full border hairline rounded-lg pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
            </div>

            <select name="category" data-auto-submit aria-label="Category" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Categories</option>
                @foreach($categories ?? [] as $category)
                    <option value="{{ $category->getKey() }}" @selected(($filters['category'] ?? null) === $category->getKey())>{{ $category->name }}</option>
                @endforeach
            </select>

            <select name="brand" data-auto-submit aria-label="Brand" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Brands</option>
                @foreach($brands ?? [] as $brand)
                    <option value="{{ $brand->getKey() }}" @selected(($filters['brand'] ?? null) === $brand->getKey())>{{ $brand->name }}</option>
                @endforeach
            </select>

            <select name="stock_status" data-auto-submit aria-label="Stock status" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Stock</option>
                <option value="in_stock" @selected(($filters['stock_status'] ?? null) === 'in_stock')>In stock</option>
                <option value="out_of_stock" @selected(($filters['stock_status'] ?? null) === 'out_of_stock')>Out of stock</option>
                <option value="on_backorder" @selected(($filters['stock_status'] ?? null) === 'on_backorder')>On backorder</option>
            </select>

            <select name="status" data-auto-submit aria-label="Status" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Statuses</option>
                <option value="active" @selected(($filters['status'] ?? null) === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Inactive</option>
            </select>

            <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">Filter</button>

            @if($activeFilterCount > 0)
                <a href="{{ route('admin.products.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Clear</a>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-3 border-t hairline">
            <label for="sort_by" class="text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Sort</label>
            <select id="sort_by" name="sort_by" data-auto-submit class="border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                @foreach([
                    'created_at' => 'Newest first',
                    'name' => 'Name',
                    'sku' => 'SKU',
                    'base_price' => 'Price',
                    'stock_status' => 'Stock status',
                    'is_active' => 'Status',
                    'is_featured' => 'Featured',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['sort_by'] ?? 'created_at') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="sort_order" data-auto-submit aria-label="Sort direction" class="border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="desc" @selected(($filters['sort_order'] ?? 'desc') === 'desc')>Descending</option>
                <option value="asc" @selected(($filters['sort_order'] ?? 'desc') === 'asc')>Ascending</option>
            </select>

            <p class="text-sm text-[var(--color-steel)] ml-auto">
                {{ number_format($products->total() ?? 0) }} product{{ ($products->total() ?? 0) === 1 ? '' : 's' }}
                @if($activeFilterCount > 0)
                    <span class="text-[var(--color-red)]">· {{ $activeFilterCount }} filter{{ $activeFilterCount === 1 ? '' : 's' }} applied</span>
                @endif
            </p>
        </div>
    </form>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Product</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Category</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Price</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Stock</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($products ?? [] as $product)
                    @php
                        $stockStyle = match ($product->stock_status?->value) {
                            'in_stock' => ['bg' => 'bg-[#1B7A43]/10 text-[#1B7A43]', 'dot' => 'bg-[#1B7A43]'],
                            'on_backorder' => ['bg' => 'bg-[#F59E0B]/10 text-[#B45309]', 'dot' => 'bg-[#F59E0B]'],
                            default => ['bg' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]', 'dot' => 'bg-[var(--color-red)]'],
                        };
                        $hasSale = $product->sale_price && $product->sale_price < $product->base_price;
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-[var(--color-paper-dim)] rounded-lg flex-shrink-0 flex items-center justify-center overflow-hidden">
                                    @if($product->primary_image)
                                        <img src="{{ asset('storage/' . $product->primary_image) }}" alt="" class="w-full h-full object-cover">
                                    @else
                                        <i class="fa-solid fa-image text-[var(--color-steel)]"></i>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-[var(--color-ink)] text-sm truncate flex items-center gap-2">
                                        {{ $product->name }}
                                        @if($product->is_featured)
                                            <i class="fa-solid fa-star text-[#F59E0B] text-xs" title="Featured"></i>
                                        @endif
                                    </p>
                                    <p class="text-xs text-[var(--color-steel)] font-mono">{{ $product->sku ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">
                            @php $categoryNames = $product->categories->pluck('name')->join(', '); @endphp
                            {{ $categoryNames ?: '—' }}
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            @if($hasSale)
                                <span class="block text-xs text-[var(--color-steel)] line-through">{{ format_currency((float) $product->base_price) }}</span>
                                <span class="font-medium text-[var(--color-red)]">{{ format_currency((float) $product->sale_price) }}</span>
                            @else
                                <span class="font-medium text-[var(--color-ink)]">{{ format_currency((float) $product->base_price) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full {{ $stockStyle['bg'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $stockStyle['dot'] }}"></span>
                                {{ $product->stock_status?->label() ?? 'Unknown' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.products.toggleStatus', $product->getKey()) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full {{ $product->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}" title="{{ $product->is_active ? 'Click to deactivate' : 'Click to activate' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $product->is_active ? 'bg-[#1B7A43]' : 'bg-[var(--color-steel)]' }}"></span>
                                    {{ $product->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.products.images', $product->getKey()) }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm" title="Manage images">Images</a>
                                <a href="{{ route('admin.products.edit', $product->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.products.destroy', $product->getKey()) }}" data-confirm="Delete &quot;{{ $product->name }}&quot;? This cannot be undone." class="inline">
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
                                <i class="fa-solid fa-box-open text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">No products match these filters</p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">
                                @if($activeFilterCount > 0)
                                    <a href="{{ route('admin.products.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Clear the filters</a> to see the full catalogue.
                                @else
                                    <a href="{{ route('admin.products.create') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Create your first product</a> to get started.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(isset($products) && method_exists($products, 'links'))
        <div class="p-4 border-t hairline">{{ $products->links() }}</div>
    @endif
</div>
@endsection