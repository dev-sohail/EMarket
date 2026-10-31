@extends('layouts.admin')

@section('title', 'Edit API Definition: {{ $definition->name }}')

@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Edit API Definition</h1>
        <div class="flex items-center gap-2 mt-1">
            <span class="font-mono text-sm text-[var(--color-steel)]">{{ $definition->slug }}</span>
            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $definition->source === 'reflection' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200' }}">
                {{ ucfirst($definition->source) }}
            </span>
        </div>
    </div>
    <a href="{{ route('admin.api-management.definitions.index') }}" class="py-2 px-4 rounded border hairline text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition text-sm">Back to list</a>
</div>
@endsection

@section('content')
<div class="max-w-4xl space-y-6">
    {{-- Definition Form --}}
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <form method="POST" action="{{ route('admin.api-management.definitions.update', $definition->getKey()) }}">
            @method('PUT')
            @include('admin.api-management.definitions._form')
        </form>
    </div>

    {{-- Standalone target for the "Regenerate from model" button rendered by the
         partial above. That button sits inside the update form, which cannot hold
         a nested form, so it references this one with the `form` attribute. --}}
    @if($definition->source === 'reflection')
        <form id="definition-regenerate-form" method="POST"
              action="{{ route('admin.api-management.definitions.regenerate', $definition->getKey()) }}"
              data-confirm="Regenerate from the model? This overwrites the name, description and the generated endpoints, and replaces their parameters.">
            @csrf
        </form>
    @endif

    {{-- Endpoints Section --}}
    <div class="bg-[var(--color-paper)] rounded-lg border hairline">
        <div class="p-6 border-b border-[var(--color-line)]">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h2 class="font-semibold text-[var(--color-ink)]">Endpoints</h2>
                    <p class="text-sm text-[var(--color-steel)] mt-1">{{ $definition->endpoints->count() }} endpoint{{ $definition->endpoints->count() !== 1 ? 's' : '' }} defined &mdash; changes take effect after <code class="px-1 py-0.5 bg-[var(--color-paper)] border hairline rounded text-xs">route:clear</code> (dev) or <code class="px-1 py-0.5 bg-[var(--color-paper)] border hairline rounded text-xs">route:cache</code> (prod).</p>
                </div>
                <a href="{{ route('admin.api-management.endpoints.create', $definition->getKey()) }}"
                   class="bg-[var(--color-red)] text-white px-4 py-2 rounded font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Add Endpoint
                </a>
            </div>
        </div>

        <div class="p-6">
            @if($definition->endpoints->isEmpty())
                <div class="text-center py-12">
                    <svg class="w-12 h-12 mx-auto text-[var(--color-steel)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    <h3 class="mt-4 text-lg font-medium text-[var(--color-ink)]">No endpoints yet</h3>
                    <p class="mt-2 text-sm text-[var(--color-steel)] max-w-md mx-auto">Add your first endpoint to expose a URL for this API definition.</p>
                    <a href="{{ route('admin.api-management.endpoints.create', $definition->getKey()) }}"
                       class="mt-6 inline-flex items-center gap-2 bg-[var(--color-red)] text-white px-4 py-2 rounded font-medium hover:bg-[var(--color-red-dark)] transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        Create Endpoint
                    </a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-[var(--color-paper-dim)]">
                            <tr>
                                <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">#</th>
                                <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Method</th>
                                <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Path</th>
                                <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Action</th>
                                <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Handler</th>
                                <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Auth</th>
                                <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                                <th class="text-right px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-line)]">
                            @foreach($definition->endpoints as $endpoint)
                                <tr class="hover:bg-[var(--color-paper)]">
                                    <td class="px-4 py-4 text-sm text-[var(--color-steel)] font-mono">{{ $endpoint->sort_order }}</td>
@php
    $methodColor = match($endpoint->method) {
        'GET'    => 'bg-green-100 text-green-800',
        'POST'   => 'bg-blue-100 text-blue-800',
        'PUT'    => 'bg-yellow-100 text-yellow-800',
        'PATCH'  => 'bg-orange-100 text-orange-800',
        'DELETE' => 'bg-red-100 text-red-800',
        default  => 'bg-gray-100 text-gray-800',
    };
@endphp
                                    <td class="px-4 py-4 text-sm font-mono text-[var(--color-ink)]">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $methodColor }}">{{ strtoupper($endpoint->method) }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-[var(--color-steel)] font-mono">{{ $endpoint->path }}</td>
                                    <td class="px-4 py-4 text-sm text-[var(--color-steel)] font-mono">{{ $endpoint->action }}</td>
                                    <td class="px-4 py-4 text-sm text-[var(--color-steel)]">{{ ucfirst(str_replace('_', ' ', $endpoint->handler_type)) }}</td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full
                                            {{ $endpoint->requires_auth ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $endpoint->requires_auth ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full
                                            {{ $endpoint->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $endpoint->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('admin.api-management.endpoints.edit', [$definition->getKey(), $endpoint->getKey()]) }}"
                                               class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                            <form method="POST"
                                                  action="{{ route('admin.api-management.endpoints.destroy', [$definition->getKey(), $endpoint->getKey()]) }}"
                                                  data-confirm="Delete endpoint {{ strtoupper($endpoint->method) }} {{ $endpoint->path }}?"
                                                  class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection