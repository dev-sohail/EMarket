@extends('layouts.admin')
@section('title', 'Create Role')
@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Create Role</h1>
    <a href="{{ route('admin.roles.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Roles</a>
</div>
@endsection
@section('content')
<form method="POST" action="{{ route('admin.roles.store') }}">
    @csrf
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-[var(--color-paper)] rounded-lg border p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Role Information</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Role Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 @error('name') border-[var(--color-red)] @enderror">
                        @error('name') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Description</label>
                        <textarea name="description" id="description-editor" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500" rows="3">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
        <div>
            <div class="bg-[var(--color-paper)] rounded-lg border p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Permissions</h2>
                <div class="space-y-4">
                    @foreach($permissions ?? [] as $group => $perms)
                        <div>
                            <p class="text-xs font-semibold text-[var(--color-steel)] uppercase mb-2">{{ $group }}</p>
                            <div class="space-y-2">
                                @foreach($perms as $perm)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="permissions[]" value="{{ $perm->getKey() }}" @checked(in_array($perm->getKey(), old('permissions', []), true)) class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-indigo-500">
                                        <span class="text-sm text-[var(--color-ink-soft)]">{{ $perm->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="w-full bg-[var(--color-red)] text-white py-3 rounded-lg font-semibold hover:bg-[var(--color-red-dark)] mt-4">Create Role</button>
        </div>
    </div>
</form>

@push('scripts')
<script src="{{ asset('vendor/ckeditor/ckeditor.js') }}"></script>
<script nonce="{{ $cspNonce }}">
    CKEDITOR.replace("description-editor");
</script>
@endpush
@endsection
