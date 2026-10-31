@extends('layouts.admin')

@section('title', 'Edit Shipping Method')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Edit Shipping Method</h1>
    <a href="{{ route('admin.shipping.methods.index') }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Back to Methods</a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.shipping.methods.update', $method->getKey()) }}" class="bg-[var(--color-paper)] rounded-lg border p-6 max-w-2xl space-y-4">
    @csrf @method('PUT')
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Name *</label>
            <input type="text" name="name" value="{{ old('name', $method->name) }}" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Base Cost ({{ currency_symbol() }}) *</label>
            <input type="number" step="0.01" name="base_cost" value="{{ old('base_cost', $method->base_cost) }}" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Per KG Cost ({{ currency_symbol() }})</label>
            <input type="number" step="0.01" name="per_kg_cost" value="{{ old('per_kg_cost', $method->per_kg_cost) }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Estimated Days</label>
            <input type="number" name="estimated_days" value="{{ old('estimated_days', $method->estimated_days) }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
        </div>
        @if($method->sort_order ?? false)
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Sort Order</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $method->sort_order) }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
        </div>
        @endif
    </div>
    <div>
        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Description</label>
        <textarea name="description" rows="3" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">{{ old('description', $method->description) }}</textarea>
    </div>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $method->is_active) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)]"><span class="text-sm">Active</span></label>
    <button type="submit" class="bg-[var(--color-red)] text-white py-2.5 px-6 rounded-lg font-medium hover:bg-[var(--color-red-dark)]">Update Method</button>
</form>
@endsection