@extends('layouts.admin')
@section('title', 'Roles & Permissions')
@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Roles & Permissions</h1>
    <a href="{{ route('admin.roles.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition">Add Role</a>
</div>
@endsection
@section('content')
<div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Role</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Permissions</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Users</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($roles ?? [] as $role)
                    <tr class="hover:bg-[var(--color-paper-dim)]">
                        <td class="px-6 py-4"><span class="font-medium text-[var(--color-ink)] text-sm">{{ $role->name }}</span></td>
                        <td class="px-6 py-4"><div class="flex flex-wrap gap-1">@foreach($role->permissions ?? [] as $perm)<span class="inline-flex px-2 py-0.5 text-xs bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] rounded">{{ $perm->name }}</span>@endforeach</div></td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $role->users_count ?? $role->users->count() }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.roles.edit', $role->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                @if(!$role->is_system)
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role->getKey()) }}" data-confirm="Delete this role?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-8 text-center text-[var(--color-steel)]">No roles found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
