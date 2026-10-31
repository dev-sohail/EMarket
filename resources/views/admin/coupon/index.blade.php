@extends('layouts.admin')
@section('title', 'Coupons')
@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Coupons</h1>
    <a href="{{ route('admin.coupons.create', []) }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition">Add Coupon</a>
</div>
@endsection
@section('content')
<div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Code</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Type</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Value</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Usage</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Expiry</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Status</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($coupons ?? [] as $coupon)
                    <tr class="hover:bg-[var(--color-paper-dim)]">
                        <td class="px-6 py-4"><span class="font-mono font-medium text-[var(--color-ink)] text-sm">{{ $coupon->code }}</span></td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ ucfirst($coupon->type) }}</td>
                        <td class="px-6 py-4 text-sm font-medium">{{ $coupon->type == 'fixed' ? '$' . number_format($coupon->value, 2) : $coupon->value . '%' }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $coupon->used_count ?? 0 }} / {{ $coupon->usage_limit ?? 'Unlimited' }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $coupon->expires_at ? $coupon->expires_at->format('M d, Y') : 'Never' }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $coupon->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]' }}">{{ $coupon->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.coupons.edit', $coupon->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.coupons.destroy', $coupon->getKey()) }}" data-confirm="Delete this coupon?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-8 text-center text-[var(--color-steel)]">No coupons found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($coupons) && method_exists($coupons, 'links'))
        <div class="p-4 border-t">{{ $coupons->links() }}</div>
    @endif
</div>
@endsection
