@extends('layouts.admin')

@section('title', 'Inventory - ' . ($inventory->product->name ?? ''))

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Inventory Details</h1>
    <a href="{{ route('admin.inventory.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Inventory</a>
</div>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-4">Product Info</h2>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center">
                        @php
                        $displayImage = $inventory->variant->image ?? $inventory->product->primary_image ?? null;
                    @endphp
                        @if($displayImage)
                            <img src="{{ asset('storage/' . $displayImage) }}" alt="{{ $inventory->product->name }}" class="w-48 h-48 object-cover rounded-lg border mx-auto">
                        @else
                            <div class="w-48 h-48 rounded-lg border bg-[var(--color-paper-dim)] flex items-center justify-center mx-auto">
                                <i class="fa-solid fa-box text-[var(--color-steel)] text-3xl"></i>
                            </div>
                        @endif
                        <p class="mt-2 text-sm text-[var(--color-steel)]">{{ $inventory->product->name }}</p>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between"><span class="text-[var(--color-steel)]">Product</span><span class="font-medium text-[var(--color-ink)]">{{ $inventory->product->name ?? '-' }}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-steel)]">Variant</span><span class="font-medium text-[var(--color-ink)]">{{ $inventory->variant->name ?? '-' }}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-steel)]">SKU</span><span class="font-mono text-[var(--color-ink)]">{{ $inventory->variant->sku ?? $inventory->product->sku ?? '-' }}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-steel)]">Weight</span><span class="font-medium text-[var(--color-ink)]">{{ $inventory->variant->weight ?? 'N/A' }}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-steel)]">Price</span><span class="font-medium text-[var(--color-ink)]">${{ $inventory->variant->price ?? '0.00' }}</span></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-4">Stock Information</h2>
            <div class="space-y-6">
                <div class="space-y-3">
                    <p class="text-sm font-medium text-[var(--color-ink-soft)]">Current Stock Level</p>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-3xl font-bold text-[var(--color-ink)]">{{ $inventory->quantity }}</p>
                            <p class="text-sm text-[var(--color-steel)]">units in stock</p>
                        </div>
                    </div>
                    <div class="w-full bg-[var(--color-paper-dim)] rounded-lg h-4 mb-2">
                        @php
                            $maxStock = $inventory->max_stock ?: 100; // fallback if max_stock not set
                            $percentage = min(($inventory->quantity / $maxStock) * 100, 100);
                            $isOut = $inventory->quantity <= 0;
                            $isLow = !$isOut && $inventory->quantity <= ($inventory->reorder_level ?: 5);
                            $barColor = $isOut ? 'bg-[var(--color-red)]' : ($isLow ? 'bg-[var(--color-amber)]' : 'bg-[#1B7A43]');
                        @endphp
                        <div class="flex-1 h-full bg-[var(--color-paper-dim)] rounded-lg overflow-hidden">
                            <div class="h-full {{ $barColor }} transition-all duration-500" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                    <div class="flex justify-between text-sm text-[var(--color-steel)]">
                        <span>0</span>
                        <span>{{ $maxStock }}</span>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <p class="text-sm font-medium text-[var(--color-ink-soft)]">Stock Status</p>
                    @php
                        $isOut = $inventory->quantity <= 0;
                        $isLow = !$isOut && $inventory->quantity <= ($inventory->reorder_level ?: 5);
                        $statusClass = $isOut ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : ($isLow ? 'bg-[var(--color-amber)]/10 text-[var(--color-amber-dark)]' : 'bg-[#1B7A43]/10 text-[#1B7A43]');
                        $statusText = $isOut ? 'Out of Stock' : ($isLow ? 'Low Stock' : 'In Stock');
                        $statusIcon = $isOut ? 'fa-circle-xmark' : ($isLow ? 'fa-triangle-exclamation' : 'fa-circle-check');
                    @endphp
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium {{ $statusClass }}">
                        <i class="fa-solid {{ $statusIcon }}"></i>
                        <span>{{ $statusText }}</span>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <p class="text-sm font-medium text-[var(--color-ink-soft)]">Reserved / Available</p>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="font-medium text-[var(--color-ink)]">{{ $inventory->reserved_quantity }}</p>
                            <p class="text-xs text-[var(--color-steel)]">Reserved</p>
                        </div>
                        <div>
                            <p class="font-medium text-[var(--color-ink)]">{{ $inventory->quantity - $inventory->reserved_quantity }}</p>
                            <p class="text-xs text-[var(--color-steel)]">Available</p>
                        </div>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <p class="text-sm font-medium text-[var(--color-ink-soft)] mb-2">Reorder Settings</p>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="font-medium text-[var(--color-ink)]">{{ $inventory->reorder_level ?? 'Not Set' }}</p>
                            <p class="text-xs text-[var(--color-steel)]">Reorder Level</p>
                        </div>
                        <div>
                            <p class="font-medium text-[var(--color-ink)]">{{ $inventory->max_stock ?? 'Not Set' }}</p>
                            <p class="text-xs text-[var(--color-steel)]">Maximum Stock</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-4">Adjust Stock</h2>
            <form method="POST" action="{{ route('admin.inventory.update', $inventory->getKey()) }}" class="space-y-4">
                @csrf @method('PUT')
                
                <div class="space-y-3">
                    <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Adjustment Type</label>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <input type="radio" id="type-increase" name="type" value="increase" class="w-4 h-4 text-[var(--color-red)] focus:ring-[var(--color-red)]" {{ old('type', 'increase') === 'increase' ? 'checked' : '' }} required>
                            <label for="type-increase" class="cursor-pointer text-sm font-medium">Increase Stock</label>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="radio" id="type-decrease" name="type" value="decrease" class="w-4 h-4 text-[var(--color-red)] focus:ring-[var(--color-red)]" {{ old('type') === 'decrease' ? 'checked' : '' }}>
                            <label for="type-decrease" class="cursor-pointer text-sm font-medium">Decrease Stock</label>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="radio" id="type-set" name="type" value="set" class="w-4 h-4 text-[var(--color-red)] focus:ring-[var(--color-red)]" {{ old('type') === 'set' ? 'checked' : '' }}>
                            <label for="type-set" class="cursor-pointer text-sm font-medium">Set to Specific Amount</label>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Quantity</label>
                    <input type="number" name="quantity" min="0" value="{{ old('quantity', 0) }}" class="w-full border rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent" required>
                    @error('quantity') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
                
                <div class="space-y-3">
                    <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Reason for Adjustment</label>
                    <select name="reason" class="w-full border rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent" required>
                        <option value="">Select a reason...</option>
                        <option value="restock" {{ old('reason') === 'restock' ? 'selected' : '' }}>Restock / Purchase</option>
                        <option value="sale" {{ old('reason') === 'sale' ? 'selected' : '' }}>Sale</option>
                        <option value="return" {{ old('reason') === 'return' ? 'selected' : '' }}>Return</option>
                        <option value="adjustment" {{ old('reason') === 'adjustment' ? 'selected' : '' }}>Manual Adjustment</option>
                        <option value="damage" {{ old('reason') === 'damage' ? 'selected' : '' }}>Damage / Spoilage</option>
                        <option value="correction" {{ old('reason') === 'correction' ? 'selected' : '' }}>Inventory Correction</option>
                        <option value="transfer" {{ old('reason') === 'transfer' ? 'selected' : '' }}>Transfer Between Locations</option>
                        <option value="other" {{ old('reason') === 'other' ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('reason') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
                
                <div class="space-y-3">
                    <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Additional Notes (Optional)</label>
                    <textarea name="note" rows="3" class="w-full border rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent" placeholder="Any additional details about this adjustment...">{{ old('note') }}</textarea>
                </div>
                
                <button type="submit" class="w-flex justify-center bg-[var(--color-red)] text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    Update Stock
                </button>
            </form>
        </div>
        
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-4">Recent Movements</h2>
            <div class="space-y-3">
                @forelse($movements ?? [] as $movement)
                    <div class="flex items-center justify-between text-sm border-b pb-3 last:border-0 last:pb-0">
                        <div>
                            <p class="font-medium text-[var(--color-ink)]">{{ ucfirst($movement->type) }}</p>
                            <p class="text-xs text-[var(--color-steel)]">{{ $movement->created_at->format('M d, Y H:i') }}</p>
                        </div>
                        <span class="font-medium {{ $movement->quantity > 0 ? 'text-[#1B7A43]' : 'text-[var(--color-red)]' }}">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</span>
                    </div>
                @empty
                    <p class="text-center text-[var(--color-steel)] text-sm py-4">No movements yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
