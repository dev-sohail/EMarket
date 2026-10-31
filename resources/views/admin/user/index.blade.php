@extends('layouts.admin')

@section('title', 'Admin Users')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Admin Users</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Back-office accounts and the roles they hold.</p>
    </div>
    <a href="{{ route('admin.users.create', []) }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center">
        <i class="fa-solid fa-user-plus mr-2"></i> Add User
    </a>
</div>
@endsection

@section('content')
@php
    $currentUserId = auth('admin')->id();
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-[var(--color-paper-dim)] flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-users text-[var(--color-steel)]"></i>
        </div>
        <div>
            <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">Total Admins</p>
            <p class="text-xl font-semibold text-[var(--color-ink)]">{{ $stats['total'] ?? 0 }}</p>
        </div>
    </div>
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-[#1B7A43]/10 flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-circle-check text-[#1B7A43]"></i>
        </div>
        <div>
            <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">Active</p>
            <p class="text-xl font-semibold text-[var(--color-ink)]">{{ $stats['active'] ?? 0 }}</p>
        </div>
    </div>
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-[var(--color-paper-dim)] flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-envelope text-[var(--color-steel)]"></i>
        </div>
        <div>
            <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">Unverified Email</p>
            <p class="text-xl font-semibold text-[var(--color-ink)]">{{ $stats['unverified'] ?? 0 }}</p>
        </div>
    </div>
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-[var(--color-paper-dim)] flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-shield-halved text-[var(--color-steel)]"></i>
        </div>
        <div>
            <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">Roles</p>
            <p class="text-xl font-semibold text-[var(--color-ink)]">{{ $stats['roles'] ?? 0 }}</p>
        </div>
    </div>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <form method="GET" action="{{ route('admin.users.index') }}" class="p-4 border-b hairline flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[220px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-steel)] text-sm"></i>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by name or email…" class="w-full border hairline rounded-lg pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
        </div>
        <select name="role" data-auto-submit class="border hairline rounded-lg px-4 py-2.5 text-sm text-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]">
            <option value="">All Roles</option>
            @foreach($roleOptions ?? [] as $roleName)
                <option value="{{ $roleName }}" @selected(request('role') === $roleName)>{{ $roleName }}</option>
            @endforeach
        </select>
        <select name="status" data-auto-submit class="border hairline rounded-lg px-4 py-2.5 text-sm text-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]">
            <option value="">All Statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            <option value="unverified" @selected(request('status') === 'unverified')>Unverified email</option>
        </select>
        <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">Filter</button>
        @if(request()->hasAny(['q', 'role', 'status']))
            <a href="{{ route('admin.users.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Clear</a>
        @endif
    </form>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">User</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Phone</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Role</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Email</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Joined</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($users ?? [] as $user)
                    @php
                        $isSelf = $currentUserId !== null && (string) $user->getKey() === (string) $currentUserId;
                        $letter = strtoupper(\Illuminate\Support\Str::of((string) $user->name)->substr(0, 1)->value() ?: '?');
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                @if($user->avatar)
                                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                                @else
                                    <div class="w-10 h-10 rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center text-[var(--color-red)] font-semibold text-sm flex-shrink-0">{{ $letter }}</div>
                                @endif
                                <div>
                                    <p class="text-sm font-medium text-[var(--color-ink)] flex items-center gap-2">
                                        {{ $user->name }}
                                        @if($isSelf)
                                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider rounded bg-[var(--color-paper-dim)] text-[var(--color-steel)]">You</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-[var(--color-steel)]">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $user->phone ?: '—' }}</td>
                        <td class="px-6 py-4">
                            @php $userRoles = $user->roles; @endphp
                            @if($userRoles->isEmpty())
                                <span class="text-sm text-[var(--color-steel)]">—</span>
                            @else
                                <div class="flex flex-wrap gap-1">
                                    @foreach($userRoles as $role)
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]">{{ $role->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($user->email_verified_at)
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-[#1B7A43]/10 text-[#1B7A43]">
                                    <i class="fa-solid fa-circle-check text-[10px]"></i> Verified
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-[var(--color-paper-dim)] text-[var(--color-steel)]">
                                    <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Unverified
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full {{ $user->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-red)]/10 text-[var(--color-red)]' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $user->is_active ? 'bg-[#1B7A43]' : 'bg-[var(--color-red)]' }}"></span>
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)] whitespace-nowrap">{{ $user->created_at?->format('M d, Y') ?? '—' }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.users.edit', $user->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                @unless($isSelf)
                                    <form method="POST" action="{{ route('admin.users.destroy', $user->getKey()) }}" data-confirm="Delete &quot;{{ $user->name }}&quot;? This cannot be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-solid fa-user-slash text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">No admin users found</p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">
                                @if(request()->hasAny(['q', 'role', 'status']))
                                    Try clearing the filters, or
                                @else
                                    Add a back-office account to
                                @endif
                                <a href="{{ route('admin.users.create', []) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">invite another user</a>.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(isset($users) && method_exists($users, 'links'))
        <div class="p-4 border-t hairline">{{ $users->links() }}</div>
    @endif
</div>
@endsection