@extends('layouts.admin')
@section('title', 'Edit Admin User')
@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Edit Admin User</h1>
    <a href="{{ route('admin.users.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Users</a>
</div>
@endsection
@section('content')
<form method="POST" action="{{ route('admin.users.update', $user) }}">
    @csrf
    @method('PUT')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">User Information</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Name *</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full border hairline rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('name') border-[var(--color-red)] @enderror">
                        @error('name') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Email *</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full border hairline rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('email') border-[var(--color-red)] @enderror">
                        @error('email') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Password</label>
                        <input type="password" name="password" class="w-full border hairline rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('password') border-[var(--color-red)] @enderror">
                        <p class="mt-1 text-xs text-[var(--color-steel)]">Leave blank to keep current password</p>
                        @error('password') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="w-full border hairline rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)]">
                    </div>
                </div>
            </div>
        </div>
        <div>
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Role & Status</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Role *</label>
                        <select name="role" required class="w-full border hairline rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('role') border-[var(--color-red)] @enderror">
                            <option value="">Select Role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}" {{ old('role', $user->roles->first()?->name) == $role->name ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                        @error('role') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                            <span class="text-sm text-[var(--color-ink-soft)]">Active</span>
                        </label>
                    </div>
                </div>
            </div>
            <button type="submit" class="w-full bg-[var(--color-red)] text-white py-3 rounded-lg font-semibold hover:bg-[var(--color-red-dark)] mt-4">Update User</button>
        </div>
    </div>
</form>
@endsection
