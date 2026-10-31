@extends('layouts.admin')

@section('title', 'Add Shipping Zone')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Add Shipping Zone</h1>
    <a href="{{ route('admin.shipping.zones.index') }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Back to Zones</a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.shipping.zones.store') }}" class="bg-[var(--color-paper)] rounded-lg border p-6 max-w-2xl space-y-4">
    @csrf
    <div>
        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Name *</label>
        <input type="text" name="name" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
    </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Cities</label>
            <div id="city-list" class="space-y-2">
                <div class="flex gap-2" data-removable><input type="text" name="cities[]" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500" placeholder="City name"><button type="button" class="text-[var(--color-red)] text-sm" data-action="remove-self">Remove</button></div>
            </div>
            <button type="button" class="mt-2 text-[var(--color-red)] text-sm" data-action="addCity">+ Add City</button>
        </div>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" checked class="rounded border-[var(--color-line)] text-[var(--color-red)]"><span class="text-sm">Active</span></label>
    <button type="submit" class="bg-[var(--color-red)] text-white py-2.5 px-6 rounded-lg font-medium hover:bg-[var(--color-red-dark)]">Save Zone</button>
</form>

@push('scripts')
<script nonce="{{ $cspNonce }}">
function addCity() {
    const list = document.getElementById('city-list');
    const div = document.createElement('div');
    div.className = 'flex gap-2';
    div.setAttribute('data-removable', '');
    div.innerHTML = '<input type="text" name="cities[]" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500" placeholder="City name"><button type="button" class="text-[var(--color-red)] text-sm" data-action="remove-self">Remove</button>';
    list.appendChild(div);
}
</script>
@endpush
@endsection
