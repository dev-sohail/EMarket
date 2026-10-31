@extends('layouts.admin')

@section('title', 'API Management')

@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">API Management</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Define the API surface this storefront exposes.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.api-management.definitions.reflect') }}" class="px-4 py-2 rounded border hairline text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition text-sm font-medium">
            Reflect from Model
        </a>
        <a href="{{ route('admin.api-management.definitions.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Add API Definition
        </a>
    </div>
</div>
@endsection

@section('content')
{{-- Dynamic endpoints are compiled into the route cache, so a change here only
     becomes a live URL after `route:clear` (dev) or `route:cache` (production).
     Saying so here prevents "I created it but it 404s" support tickets. --}}
<div class="mb-4 p-4 border hairline bg-[var(--color-paper-dim)] rounded-lg text-sm text-[var(--color-steel)] flex items-start gap-2">
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <div>
        After creating or changing a definition, run
        <code class="px-1 py-0.5 bg-[var(--color-paper)] border hairline rounded text-xs">php artisan route:clear</code>
        (development) or
        <code class="px-1 py-0.5 bg-[var(--color-paper)] border hairline rounded text-xs">php artisan route:cache</code>
        (production). Until then new endpoints answer <strong>404</strong>, because dynamic routes are baked into the route cache at boot.
    </div>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Name</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Slug</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Source</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Endpoints</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($definitions ?? [] as $definition)
                    <tr class="hover:bg-[var(--color-paper)]">
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.api-management.definitions.show', $definition->getKey()) }}"
                               class="text-sm font-medium text-[var(--color-ink)] hover:underline">{{ $definition->name }}</a>
                            @if($definition->description)
                                <div class="text-xs text-[var(--color-steel)] mt-0.5">{{ Str::limit($definition->description, 80) }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)] font-mono">{{ $definition->slug }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ ucfirst($definition->source) }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $definition->endpoints_count }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $definition->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}">
                                {{ $definition->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.api-management.definitions.show', $definition->getKey()) }}" class="text-[var(--color-ink)] hover:underline text-sm">View</a>
                                <a href="{{ route('admin.api-management.definitions.edit', $definition->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.api-management.definitions.destroy', $definition->getKey()) }}" data-confirm="Delete the {{ $definition->name }} API definition and all of its endpoints?" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-[var(--color-steel)]">No API definitions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
