@extends('layouts.admin')

@section('title', 'Audit Logs')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Audit Logs</h1>
</div>
@endsection

@section('content')
<form method="GET" action="{{ route('admin.audit-logs.index') }}" class="mb-6">
    <div class="bg-[var(--color-paper)] rounded-lg border p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-[var(--color-steel)] uppercase mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search action or model..." class="w-full border hairline bg-[var(--color-paper)] px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--color-steel)] uppercase mb-1">Action</label>
                <select name="action" class="w-full border hairline bg-[var(--color-paper)] px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                    <option value="">All Actions</option>
                    <option value="created" {{ ($filters['action'] ?? '') === 'created' ? 'selected' : '' }}>Created</option>
                    <option value="updated" {{ ($filters['action'] ?? '') === 'updated' ? 'selected' : '' }}>Updated</option>
                    <option value="deleted" {{ ($filters['action'] ?? '') === 'deleted' ? 'selected' : '' }}>Deleted</option>
                    <option value="reflected" {{ ($filters['action'] ?? '') === 'reflected' ? 'selected' : '' }}>Reflected</option>
                    <option value="regenerated" {{ ($filters['action'] ?? '') === 'regenerated' ? 'selected' : '' }}>Regenerated</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--color-steel)] uppercase mb-1">Model</label>
                <input type="text" name="entity_type" value="{{ $filters['entity_type'] ?? '' }}" placeholder="e.g. Product" class="w-full border hairline bg-[var(--color-paper)] px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--color-steel)] uppercase mb-1">Date Range</label>
                <div class="flex gap-2">
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full border hairline bg-[var(--color-paper)] px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full border hairline bg-[var(--color-paper)] px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                </div>
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button type="submit" class="px-4 py-2 bg-[var(--color-red)] text-white text-sm font-medium rounded hover:bg-[var(--color-red-dark)] transition">Apply Filters</button>
            @if(request()->query())
                <a href="{{ route('admin.audit-logs.index') }}" class="px-4 py-2 bg-[var(--color-paper-dim)] text-[var(--color-ink)] text-sm font-medium rounded hover:bg-[var(--color-line)] transition">Clear</a>
            @endif
        </div>
    </div>
</form>

<div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">User</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Action</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Model</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Date</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($logs ?? [] as $log)
                    <tr class="hover:bg-[var(--color-paper-dim)]">
                        <td class="px-6 py-4 text-sm text-[var(--color-ink)]">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-6 py-4">
                            @php
                                $actionColors = [
                                    'created' => 'bg-[#1B7A43]/10 text-[#1B7A43]',
                                    'updated' => 'bg-[var(--color-paper-dim)] text-[var(--color-red)]',
                                    'deleted' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]',
                                    // Generated APIs get their own colours: they
                                    // publish a whole surface at once, so they
                                    // should be distinguishable from a hand edit.
                                    'reflected' => 'bg-blue-100 text-blue-800',
                                    'regenerated' => 'bg-purple-100 text-purple-800',
                                ];
                                $color = $actionColors[$log->action] ?? 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]';
                            @endphp
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $color }}">{{ ucfirst($log->action) }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">
                            <span class="font-medium">{{ class_basename($log->entity_type) }}</span>
                            @if($log->entity_id)
                                <span class="text-[var(--color-steel)]">#{{ $log->entity_id }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $log->created_at?->format('M d, Y H:i') ?? '-' }}</td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('admin.audit-logs.show', $log->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-[var(--color-steel)]">No audit logs found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($logs) && method_exists($logs, 'links'))
        <div class="p-4 border-t">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
