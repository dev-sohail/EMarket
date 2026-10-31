@extends('layouts.admin')
@section('title', 'Edit Coupon')
@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Edit Coupon</h1>
    <a href="{{ route('admin.coupons.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Coupons</a>
</div>
@endsection
@section('content')
<form method="POST" action="{{ route('admin.coupons.update', $coupon) }}">
    @csrf
    @method('PUT')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-[var(--color-paper)] rounded-lg border p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Coupon Information</h2>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Code *</label>
                            <input type="text" name="code" value="{{ old('code', $coupon->code) }}" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 uppercase @error('code') border-[var(--color-red)] @enderror">
                            @error('code') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Type *</label>
                            <select name="type" id="coupon-type" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                                <option value="percentage" {{ old('type', $coupon->type) == 'percentage' ? 'selected' : '' }}>Percentage</option>
                                <option value="fixed" {{ old('type', $coupon->type) == 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                                <option value="free_shipping" {{ old('type', $coupon->type) == 'free_shipping' ? 'selected' : '' }}>Free Shipping</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4" id="value-row">
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1" id="value-label">Value *</label>
                            <input type="number" name="value" id="coupon-value" value="{{ old('value', $coupon->value) }}" step="0.01" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 @error('value') border-[var(--color-red)] @enderror">
                            @error('value') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Minimum Order ({{ currency_symbol() }})</label>
                            <input type="number" name="minimum_order" value="{{ old('minimum_order', $coupon->minimum_order) }}" step="0.01" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Maximum Discount ({{ currency_symbol() }})</label>
                            <input type="number" name="maximum_discount" value="{{ old('maximum_discount', $coupon->maximum_discount) }}" step="0.01" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Usage Limit</label>
                            <input type="number" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit) }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Per Customer Limit</label>
                            <input type="number" name="per_customer_limit" value="{{ old('per_customer_limit', $coupon->per_customer_limit) }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div>
            <div class="bg-[var(--color-paper)] rounded-lg border p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Schedule & Status</h2>
                <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Starts At</label>
                            <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $coupon->starts_at ? \Carbon\Carbon::parse($coupon->starts_at)->->format('Y-m-d\TH:i') : '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Expires At</label>
                            <input type="datetime-local" name="expires_at" value="{{ old('expires_at', $coupon->expires_at ? \Carbon\Carbon::parse($coupon->expires_at)->->format('Y-m-d\TH:i') : '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $coupon->is_active) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-indigo-500">
                            <span class="text-sm text-[var(--color-ink-soft)]">Active</span>
                        </label>
                    </div>
                </div>
            </div>
            <button type="submit" class="w-full bg-[var(--color-red)] text-white py-3 rounded-lg font-semibold hover:bg-[var(--color-red-dark)] mt-4">Update Coupon</button>
        </div>
    </div>
</form>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">
function syncCouponType() {
    const type = document.getElementById('coupon-type').value;
    const valueRow = document.getElementById('value-row');
    const valueInput = document.getElementById('coupon-value');
    const valueLabel = document.getElementById('value-label');
    if (type === 'free_shipping') {
        valueRow.style.display = 'none';
        valueInput.removeAttribute('required');
        valueInput.value = 0;
    } else {
        valueRow.style.display = '';
        valueInput.setAttribute('required', 'required');
        valueLabel.textContent = type === 'percentage' ? 'Discount Percentage (%) *' : 'Discount Amount ({{ currency_symbol() }}) *';
    }
}
document.getElementById('coupon-type').addEventListener('change', syncCouponType);
syncCouponType();
</script>
@endpush
