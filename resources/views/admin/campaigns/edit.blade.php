@extends('layouts.admin')

@section('title', 'Edit Campaign')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Edit Campaign</h1>
    <a href="{{ route('admin.campaigns.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Campaigns</a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.campaigns.update', $campaign) }}">
    @csrf
    @method('PUT')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Campaign Information --}}
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-display font-semibold text-[var(--color-ink)] mb-1">Campaign Information</h2>
                <p class="text-xs text-[var(--color-steel)] mb-4">Define the discount type and how much to take off.</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Name *</label>
                        <input type="text" name="name" value="{{ old('name', $campaign->name) }}" required
                            class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 rounded-lg focus:outline-none focus:border-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]/20 @error('name') border-[var(--color-red)] @enderror">
                        @error('name') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Type *</label>
                            <select name="type" id="type" data-action="updateValueHelper" required
                                class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 rounded-lg focus:outline-none focus:border-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]/20 @error('type') border-[var(--color-red)] @enderror">
                                <option value="percentage" {{ old('type', $campaign->type) == 'percentage' ? 'selected' : '' }}>Percentage</option>
                                <option value="fixed" {{ old('type', $campaign->type) == 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                            </select>
                            @error('type') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Value *</label>
                            <input type="number" name="value" value="{{ old('value', $campaign->value) }}" step="0.01" min="0" required
                                class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 rounded-lg focus:outline-none focus:border-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]/20 @error('value') border-[var(--color-red)] @enderror">
                            <p class="text-xs text-[var(--color-steel)] mt-1"
                                data-value-helper
                                data-fixed-text="Deduct this amount ({{ currency_symbol() }}) from each product."
                                data-pct-text="Deduct this percentage (%) from each product.">
                                @if(old('type', $campaign->type) === 'fixed')
                                    Deduct this amount ({{ currency_symbol() }}) from each product.
                                @else
                                    Deduct this percentage (%) from each product.
                                @endif
                            </p>
                            @error('value') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Minimum Order ({{ currency_symbol() }})</label>
                        <input type="number" name="minimum_order" value="{{ old('minimum_order', $campaign->minimum_order) }}" step="0.01" min="0"
                            class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 rounded-lg focus:outline-none focus:border-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]/20">
                        <p class="text-xs text-[var(--color-steel)] mt-1">Customers must spend at least this much for the campaign to apply.</p>
                    </div>
                </div>
            </div>

            {{-- Products --}}
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-display font-semibold text-[var(--color-ink)] mb-1">Products</h2>
                <p class="text-xs text-[var(--color-steel)] mb-3">Select individual products to include. Leave a campaign price blank to auto-calculate from the discount above.</p>
                <div class="border border-[var(--color-line)] rounded-lg overflow-hidden">
                    <div class="max-h-72 overflow-y-auto">
                        @php
                            // pluck('getKey') yields nulls on an Eloquent collection,
                            // and update() syncs exactly what this form posts: every box
                            // rendered unchecked, so saving a campaign wiped all of its
                            // products.
                            $selectedProducts = old('product_ids', $campaign->products->map->getKey()->all());
                            $productPrices = $campaign->products->mapWithKeys(fn ($p) => [$p->getKey() => $p->pivot->campaign_price ?? ''])->toArray();
                        @endphp
                        @foreach($products as $product)
                            @php
                                $campaignPrice = old('product_prices.' . $product->getKey(), $productPrices[$product->getKey()] ?? '');
                                if ($campaignPrice === null || $campaignPrice === '') {
                                    if ($campaign->type === 'fixed') {
                                        $campaignPrice = max(0, round($product->base_price - ($campaign->value ?? 0), 2));
                                    } elseif ($campaign->type === 'percentage') {
                                        $campaignPrice = max(0, round($product->base_price * (1 - ($campaign->value ?? 0) / 100), 2));
                                    } else {
                                        $campaignPrice = '';
                                    }
                                }
                            @endphp
                            <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-[var(--color-line)] last:border-b-0">
                                <label class="flex items-center gap-2.5 cursor-pointer flex-1 min-w-0">
                                    <input type="checkbox" name="product_ids[]" value="{{ $product->getKey() }}"
                                        {{ in_array($product->getKey(), $selectedProducts) ? 'checked' : '' }}
                                        class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)] mt-0.5">
                                    <span class="text-sm text-[var(--color-ink-soft)] truncate">{{ $product->name }}</span>
                                </label>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-xs text-[var(--color-steel)]">{{ format_currency($product->base_price) }}</span>
                                    <input type="number" name="product_prices[{{ $product->getKey() }}]"
                                        value="{{ $campaignPrice }}"
                                        step="0.01" min="0"
                                        placeholder="Campaign price"
                                        class="w-28 border hairline bg-[var(--color-paper)] px-2.5 py-1.5 text-sm rounded focus:outline-none focus:border-[var(--color-ink)] focus:ring-1 focus:ring-[var(--color-red)]/20">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Categories --}}
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-display font-semibold text-[var(--color-ink)] mb-1">Categories</h2>
                <p class="text-xs text-[var(--color-steel)] mb-3">Include every product within these categories in the campaign.</p>
                <div class="border border-[var(--color-line)] rounded-lg overflow-hidden">
                    <div class="max-h-72 overflow-y-auto">
                        @php $selectedCategories = old('category_ids', $campaign->categories->map->getKey()->all()); @endphp
                        @foreach($categories as $category)
                            <label class="flex items-center gap-2.5 cursor-pointer px-4 py-2.5 border-b border-[var(--color-line)] last:border-b-0">
                                <input type="checkbox" name="category_ids[]" value="{{ $category->getKey() }}"
                                    {{ in_array($category->getKey(), $selectedCategories) ? 'checked' : '' }}
                                    class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)] mt-0.5">
                                <span class="text-sm text-[var(--color-ink-soft)]">{{ $category->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div>
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 sticky top-6">
                <h2 class="font-display font-semibold text-[var(--color-ink)] mb-1">Schedule & Status</h2>
                <p class="text-xs text-[var(--color-steel)] mb-4">Set when the campaign runs and whether it is active now.</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Starts At</label>
                        <input type="datetime-local" name="starts_at"
                            value="{{ old('starts_at', $campaign->starts_at ? \Carbon\Carbon::parse($campaign->starts_at)->format('Y-m-d\TH:i') : '') }}"
                            class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 rounded-lg focus:outline-none focus:border-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]/20 @error('starts_at') border-[var(--color-red)] @enderror">
                        @error('starts_at') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Ends At</label>
                        <input type="datetime-local" name="ends_at"
                            value="{{ old('ends_at', $campaign->ends_at ? \Carbon\Carbon::parse($campaign->ends_at)->format('Y-m-d\TH:i') : '') }}"
                            class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 rounded-lg focus:outline-none focus:border-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]/20 @error('ends_at') border-[var(--color-red)] @enderror">
                        @error('ends_at') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $campaign->is_active) ? 'checked' : '' }}
                                class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                            <span class="text-sm text-[var(--color-ink)]">Active</span>
                        </label>
                    </div>
                </div>
                <button type="submit"
                    class="w-full bg-[var(--color-red)] text-white py-3 rounded-lg font-semibold hover:bg-[var(--color-red-dark)] transition mt-6 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Update Campaign
                </button>
            </div>
        </div>
    </div>
</form>
@endsection
