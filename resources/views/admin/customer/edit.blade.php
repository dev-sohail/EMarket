@extends('layouts.admin')
@section('title', 'Edit Customer')
@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Edit Customer</h1>
    <a href="{{ route('admin.customers.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Customers</a>
</div>
@endsection
@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="bg-[var(--color-paper)] rounded-lg border p-6 space-y-4">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">First Name *</label>
                <input type="text" name="first_name" value="{{ old('first_name', $customer->first_name) }}" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 @error('first_name') border-[var(--color-red)] @enderror">
                @error('first_name')<p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Last Name *</label>
                <input type="text" name="last_name" value="{{ old('last_name', $customer->last_name) }}" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 @error('last_name') border-[var(--color-red)] @enderror">
                @error('last_name')<p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Email *</label>
            <input type="email" name="email" value="{{ old('email', $customer->email) }}" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 @error('email') border-[var(--color-red)] @enderror">
            @error('email')<p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $customer->is_active) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)]">
            <label class="text-sm text-[var(--color-ink-soft)]">Active</label>
        </div>
        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.customers.index') }}" class="px-4 py-2 border rounded-lg text-sm font-medium text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)]">Cancel</a>
            <button type="submit" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)]">Update Customer</button>
        </div>
    </form>
</div>
@endsection