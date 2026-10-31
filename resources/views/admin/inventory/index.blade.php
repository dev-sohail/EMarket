@extends('layouts.admin')

@section('title', 'Inventory')

@section('header')
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Inventory</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Manage stock levels and track inventory movements</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.inventory.movements') }}" class="bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-line)] transition">Movements</a>
        <a href="{{ route('admin.inventory.low-stock') }}" class="bg-[var(--color-red)]/10 text-[var(--color-red-dark)] px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red)]/20 transition flex items-center gap-1">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Low Stock ({{ $stats['lowStockCount'] }})
        </a>
    </div>
</div>
@endsection

@section('content')
<div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
    {{-- Search and Filters --}}
    <div class="p-4 border-b bg-[var(--color-paper-dim)]/50">
        <form method="GET" action="{{ route('admin.inventory.index') }}" x-data="{ open: false }" class="space-y-4">
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="relative flex-1 max-w-md">
                    <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-steel)]"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products, variants, SKUs..." class="w-full pl-10 pr-4 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent">
                </div>
                
                <div class="flex flex-wrap gap-2">
                    <select name="status" class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent" x-ref="statusSelect">
                        <option value="">All Status</option>
                        <option value="out" {{ request('status') === 'out' ? 'selected' : '' }}>Out of Stock</option>
                        <option value="low" {{ request('status') === 'low' ? 'selected' : '' }}>Low Stock</option>
                        <option value="in" {{ request('status') === 'in' ? 'selected' : '' }}>In Stock</option>
                    </select>
                    
                    <select name="sort" class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent">
                        <option value="latest" {{ request('sort') === 'latest' ? 'selected' : '' }}>Newest First</option>
                        <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest First</option>
                        <option value="qty_asc" {{ request('sort') === 'qty_asc' ? 'selected' : '' }}>Quantity: Low to High</option>
                        <option value="qty_desc" {{ request('sort') === 'qty_desc' ? 'selected' : '' }}>Quantity: High to Low</option>
                        <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>Name: A-Z</option>
                        <option value="name_desc" {{ request('sort') === 'name_desc' ? 'selected' : '' }}>Name: Z-A</option>
                    </select>
                    
                    <button type="submit" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-1">
                        <i class="fa-solid fa-filter"></i> Filter
                    </button>
                    
                    @if(request()->hasAny(['search', 'status', 'sort']) && (request('search') || request('status') || request('sort') !== 'latest'))
                        <a href="{{ route('admin.inventory.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-1">
                            <i class="fa-solid fa-xmark"></i> Clear
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- Stats Summary --}}
    <div class="px-4 py-3 border-b bg-[var(--color-paper-dim)]/30">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            <div class="p-3 bg-[var(--color-red)]/10 rounded-lg">
                <div class="text-2xl font-bold text-[var(--color-red)]">{{ $stats['total'] }}</div>
                <div class="text-xs text-[var(--color-steel)]">Total Items</div>
            </div>
            <div class="p-3 bg-[var(--color-red)]/10 rounded-lg">
                <div class="text-2xl font-bold text-[var(--color-red)]">{{ $stats['outOfStockCount'] }}</div>
                <div class="text-xs text-[var(--color-steel)]">Out of Stock</div>
            </div>
            <div class="p-3 bg-[var(--color-amber)]/10 rounded-lg">
                <div class="text-2xl font-bold text-[var(--color-amber-dark)]">{{ $stats['lowStockCount'] }}</div>
                <div class="text-xs text-[var(--color-steel)]">Low Stock</div>
            </div>
            <div class="p-3 bg-[#1B7A43]/10 rounded-lg">
                <div class="text-2xl font-bold text-[#1B7A43]">{{ $stats['inStockCount'] }}</div>
                <div class="text-xs text-[var(--color-steel)]">In Stock</div>
            </div>
        </div>
    </div>

    {{-- Inventory Table --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase w-24">Image</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Product / Variant</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">SKU</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase w-28">Qty</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase w-28">Reserved</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase w-32">Available</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase w-36">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase w-40">Reorder / Max</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase w-28">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($products ?? [] as $item)
                    @php
                        $available = $item->quantity - $item->reserved_quantity;
                        $isOut = $item->quantity <= 0;
                        $isLow = !$isOut && $item->quantity <= ($lowStockThreshold ?? $item->reorder_level ?? 5);
                        $statusClass = $isOut ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : ($isLow ? 'bg-[var(--color-amber)]/10 text-[var(--color-amber-dark)]' : 'bg-[#1B7A43]/10 text-[#1B7A43]');
                        $statusText = $isOut ? 'Out of Stock' : ($isLow ? 'Low Stock' : 'In Stock');
                        $statusIcon = $isOut ? 'fa-circle-xmark' : ($isLow ? 'fa-triangle-exclamation' : 'fa-circle-check');
                        $displayImage = $item->variant->image ?? $item->product->primary_image ?? null;
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] {{ $isOut ? 'bg-[var(--color-red)]/5' : ($isLow ? 'bg-[var(--color-amber)]/5' : '') }}">
                        <td class="px-6 py-4">
                            @if($displayImage)
                                <img src="{{ asset('storage/' . $displayImage) }}" alt="{{ $item->product->name }}" class="w-12 h-12 object-cover rounded-lg border">
                            @else
                                <div class="w-12 h-12 rounded-lg border bg-[var(--color-paper-dim)] flex items-center justify-center">
                                    <i class="fa-solid fa-box text-[var(--color-steel)] text-xl"></i>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-[var(--color-ink)]">{{ $item->product->name ?? '-' }}</div>
                            @if($item->variant)
                                <div class="text-xs text-[var(--color-steel)]">{{ $item->variant->name }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)] font-mono">
                            {{ $item->variant->sku ?? $item->product->sku ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-[var(--color-ink)]">{{ $item->quantity }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $item->reserved_quantity }}</td>
                        <td class="px-6 py-4 text-sm font-medium {{ $available <= 0 ? 'text-[var(--color-red)]' : 'text-[#1B7A43]' }}">{{ $available }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full {{ $statusClass }}">
                                <i class="fa-solid {{ $statusIcon }}"></i>
                                {{ $statusText }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">
                            <div>Reorder: {{ $item->reorder_level ?? '—' }}</div>
                            <div>Max: {{ $item->max_stock ?? '—' }}</div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('admin.inventory.show', $item->getKey()) }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-[var(--color-red)] text-white text-sm font-medium rounded-lg hover:bg-[var(--color-red-dark)] transition">
                                <i class="fa-solid fa-eye"></i> Adjust
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center">
                            <i class="fa-solid fa-box-open text-4xl text-[var(--color-steel)]/50 mb-3 block"></i>
                            <p class="text-[var(--color-steel)]">No inventory items found.</p>
                            @if(request()->hasAny(['search', 'status']))
                                <p class="text-sm text-[var(--color-steel)] mt-1">Try adjusting your filters or <a href="{{ route('admin.inventory.index') }}" class="text-[var(--color-red)] hover:underline">clear filters</a>.</p>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if(method_exists($products, 'links'))
        <div class="p-4 border-t">{{ $products->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
