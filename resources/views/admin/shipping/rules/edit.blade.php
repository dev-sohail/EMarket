@extends('layouts.admin')

@section('title', 'Edit Shipping Rule')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Edit Shipping Rule: {{ $zone->name }}</h1>
    <a href="{{ route('admin.shipping.rules.index', $zone->getKey()) }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Back to Rules</a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.shipping.rules.update', $rule->getKey()) }}" class="bg-[var(--color-paper)] rounded-lg border p-6 max-w-2xl space-y-4">
    @csrf @method('PUT')
    <div>
        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Shipping Method *</label>
        <select name="shipping_method_id" required class="w-full border rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-500">
            <option value="">Select Method</option>
            @foreach($methods as $m)
                <option value="{{ $m->getKey() }}" {{ old('shipping_method_id', $rule->shipping_method_id) == $m->getKey() ? 'selected' : '' }}>{{ $m->name }}</option>
            @endforeach
        </select>
        @error('shipping_method_id') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Min Weight</label>
            <input type="number" step="0.01" name="min_weight" value="{{ old('min_weight', $rule->min_weight) }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
            @error('min_weight') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Max Weight</label>
            <input type="number" step="0.01" name="max_weight" value="{{ old('max_weight', $rule->max_weight) }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
            @error('max_weight') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Min Order Value</label>
            <input type="number" step="0.01" name="min_order_value" value="{{ old('min_order_value', $rule->min_order_value) }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
            @error('min_order_value') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Max Order Value</label>
            <input type="number" step="0.01" name="max_order_value" value="{{ old('max_order_value', $rule->max_order_value) }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
            @error('max_order_value') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Price *</label>
            <input type="number" step="0.01" name="price" value="{{ old('price', $rule->price) }}" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
            @error('price') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Free Shipping Threshold</label>
            <input type="number" step="0.01" name="free_shipping_threshold" value="{{ old('free_shipping_threshold', $rule->free_shipping_threshold) }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
            @error('free_shipping_threshold') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
    </div>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $rule->is_active) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)]"><span class="text-sm">Active</span></label>
    <button type="submit" class="bg-[var(--color-red)] text-white py-2.5 px-6 rounded-lg font-medium hover:bg-[var(--color-red-dark)]">Update Rule</button>
</form>
@endsection