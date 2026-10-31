@extends('layouts.admin')

@section('title', 'Audit Log #' . ($auditLog->getKey() ?? ''))

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Audit Log #{{ $auditLog->getKey() }}</h1>
    <a href="{{ route('admin.audit-logs.index') }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Back to Logs</a>
</div>
@endsection

@section('content')
<div class="space-y-6">
    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <h2 class="font-semibold text-[var(--color-ink)] mb-4">Details</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-[var(--color-steel)]">User</dt>
                <dd class="text-[var(--color-ink)] font-medium">{{ $auditLog->user?->name ?? 'System' }}</dd>
            </div>
            <div>
                <dt class="text-[var(--color-steel)]">Action</dt>
                <dd class="text-[var(--color-ink)] font-medium">
                    @php
                        $actionColors = [
                            'created' => 'bg-[#1B7A43]/10 text-[#1B7A43]',
                            'updated' => 'bg-[var(--color-paper-dim)] text-[var(--color-red)]',
                            'deleted' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]',
                            'reflected' => 'bg-blue-100 text-blue-800',
                            'regenerated' => 'bg-purple-100 text-purple-800',
                        ];
                        $color = $actionColors[$auditLog->action] ?? 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]';
                    @endphp
                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $color }}">{{ ucfirst($auditLog->action) }}</span>
                </dd>
            </div>
            <div>
                <dt class="text-[var(--color-steel)]">Model</dt>
                <dd class="text-[var(--color-ink)] font-medium">{{ class_basename($auditLog->entity_type) }} #{{ $auditLog->entity_id }}</dd>
            </div>
            <div>
                <dt class="text-[var(--color-steel)]">Date</dt>
                <dd class="text-[var(--color-ink)] font-medium">{{ $auditLog->created_at?->format('M d, Y H:i:s') ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-[var(--color-steel)]">IP Address</dt>
                <dd class="text-[var(--color-ink)] font-medium">{{ $auditLog->ip_address ?? '-' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[var(--color-steel)]">User Agent</dt>
                <dd class="text-[var(--color-ink)] font-medium break-all">{{ $auditLog->user_agent ?? '-' }}</dd>
            </div>
        </dl>
    </div>

    @php
        $oldValues = $auditLog->old_values ?? [];
        $newValues = $auditLog->new_values ?? [];
        $changedKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
    @endphp

    <div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h2 class="font-semibold text-[var(--color-ink)]">Changes</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[var(--color-paper-dim)]">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Field</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Previous</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">New</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($changedKeys as $key)
                        @php
                            $old = $oldValues[$key] ?? null;
                            $new = $newValues[$key] ?? null;
                            $oldJson = json_encode($old);
                            $newJson = json_encode($new);
                            $changed = $oldJson !== $newJson;
                        @endphp
                        @if($changed)
                            <tr class="hover:bg-[var(--color-paper-dim)]">
                                <td class="px-6 py-3 text-sm font-medium text-[var(--color-ink)]">{{ $key }}</td>
                                <td class="px-6 py-3 text-sm text-[var(--color-steel)]">
                                    @if(is_array($old))
                                        <pre class="text-xs bg-[var(--color-red)]/5 text-[var(--color-red)] p-2 rounded">{{ json_encode($old, JSON_PRETTY_PRINT) }}</pre>
                                    @else
                                        <span class="line-through text-[var(--color-red)]">{{ $oldJson === 'null' ? '—' : $old }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm text-[var(--color-steel)]">
                                    @if(is_array($new))
                                        <pre class="text-xs bg-[#1B7A43]/5 text-[#1B7A43] p-2 rounded">{{ json_encode($new, JSON_PRETTY_PRINT) }}</pre>
                                    @else
                                        <span class="text-[#1B7A43]">{{ $newJson === 'null' ? '—' : $new }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-[var(--color-steel)]">No changes recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
