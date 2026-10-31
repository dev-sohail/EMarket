@extends('layouts.admin')

@section('title', 'Shipping Rules')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Shipping Rules: {{ $zone->name }}</h1>
    <a href="{{ route('admin.shipping.zones.index') }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Back to Zones</a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.shipping.rules.store') }}" class="bg-[var(--color-paper)] rounded-lg border p-6 mb-6 max-w-2xl space-y-4">
    @csrf
    <input type="hidden" name="zone_id" value="{{ $zone->getKey() }}">
    <div>
        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Shipping Method *</label>
        <select name="shipping_method_id" required class="w-full border rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-500">
            <option value="">Select Method</option>
            @foreach($methods as $m)
                <option value="{{ $m->getKey() }}">{{ $m->name }}</option>
            @endforeach
        </select>
        @error('shipping_method_id') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Min Weight</label>
            <input type="number" step="0.01" name="min_weight" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
            @error('min_weight') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Max Weight</label>
            <input type="number" step="0.01" name="max_weight" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
            @error('max_weight') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Min Order Value</label>
            <input type="number" step="0.01" name="min_order_value" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
            @error('min_order_value') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Max Order Value</label>
            <input type="number" step="0.01" name="max_order_value" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
            @error('max_order_value') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Price *</label>
            <input type="number" step="0.01" name="price" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
            @error('price') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Free Shipping Threshold</label>
            <input type="number" step="0.01" name="free_shipping_threshold" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
            @error('free_shipping_threshold') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
    </div>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" checked class="rounded border-[var(--color-line)] text-[var(--color-red)]"><span class="text-sm">Active</span></label>
    <button type="submit" class="bg-[var(--color-red)] text-white py-2.5 px-6 rounded-lg font-medium hover:bg-[var(--color-red-dark)]">Add Rule</button>
</form>

<div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Method</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Weight Range</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Order Value Range</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Cost</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($zone->rules as $rule)
                    <tr class="hover:bg-[var(--color-paper-dim)]">
                        <td class="px-6 py-4 text-sm font-medium text-[var(--color-ink)]">{{ $rule->method->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $rule->min_weight ?? '0' }} - {{ $rule->max_weight ?? '∞' }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $rule->min_order_value ?? '0' }} - {{ $rule->max_order_value ?? '∞' }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-ink)]">{{ format_currency($rule->price) }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.shipping.rules.edit', $rule->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.shipping.rules.destroy', $rule->getKey()) }}" data-confirm="Delete this rule?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-[var(--color-steel)]">No rules defined for this zone.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection