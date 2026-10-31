@extends('layouts.admin')
@section('title', 'Notifications')
@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Notifications</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">
            @if($unreadCount > 0)
                You have <span class="font-medium text-[var(--color-red)]">{{ $unreadCount }}</span> unread notification(s).
            @else
                All caught up!
            @endif
        </p>
    </div>
    <div class="flex gap-2">
        <form method="POST" action="{{ route('admin.notifications.mark-read') }}" class="inline">
            @csrf
            <button type="submit" class="bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-line)] transition">Mark All as Read</button>
        </form>
    </div>
</div>
@endsection
@section('content')
<form method="GET" action="{{ route('admin.notifications.index') }}" class="mb-6">
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1 max-w-md">
            <div class="relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search notifications..." class="w-full border hairline bg-[var(--color-paper)] pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                <svg class="absolute left-3 top-2.5 h-5 w-5 text-[var(--color-steel)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
        </div>
        <div class="flex gap-2">
            <select name="status" class="border hairline bg-[var(--color-paper)] px-4 py-2.5 text-sm focus:outline-none focus:border-[var(--color-ink)]" data-auto-submit>
                <option value="">All Status</option>
                <option value="unread" {{ request('status') === 'unread' ? 'selected' : '' }}>Unread</option>
                <option value="read" {{ request('status') === 'read' ? 'selected' : '' }}>Read</option>
            </select>
            <button type="submit" class="px-4 py-2.5 bg-[var(--color-red)] text-white text-sm font-medium rounded hover:bg-[var(--color-red-dark)] transition">Filter</button>
            @if(request('search') || request('status'))
                <a href="{{ route('admin.notifications.index') }}" class="px-4 py-2.5 bg-[var(--color-paper-dim)] text-[var(--color-ink)] text-sm font-medium rounded hover:bg-[var(--color-line)] transition">Clear</a>
            @endif
        </div>
    </div>
</form>

{{-- Bulk Actions --}}
<form method="POST" action="{{ route('admin.notifications.bulk-read') }}" id="bulk-form-read" class="mb-4">
    @csrf
    <div class="bg-[var(--color-paper)] rounded-lg border p-4 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-[var(--color-steel)] cursor-pointer">
                <input type="checkbox" id="select-all" class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                Select All
            </label>
            <span class="text-sm text-[var(--color-steel)]">|</span>
            <span class="text-sm text-[var(--color-steel)]" id="selected-count">0 selected</span>
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition disabled:opacity-50 disabled:cursor-not-allowed" id="bulk-read-submit" disabled>
                Mark Selected as Read
            </button>
            <button type="button" class="bg-[var(--color-paper-dim)] text-[var(--color-ink)] px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-line)] transition disabled:opacity-50 disabled:cursor-not-allowed" id="bulk-delete-submit" disabled>
                Delete Selected
            </button>
        </div>
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
        <div class="divide-y">
            @forelse($notifications as $notification)
                @php
                    $data = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
                    $title = $data['title'] ?? 'Notification';
                    $message = $data['message'] ?? '';
                    $time = \Carbon\Carbon::parse($notification->created_at)->format('M d, Y h:i A');
                @endphp
                <div class="p-4 hover:bg-[var(--color-paper-dim)] {{ is_null($notification->read_at) ? 'bg-[var(--color-paper-dim)]' : '' }}">
                    <div class="flex items-start gap-3">
                        <div class="flex items-center pt-1">
                            <input type="checkbox" name="notification_ids[]" value="{{ $notification->id }}" class="notification-checkbox rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-[var(--color-ink)]">{{ $title }}</p>
                                @if(is_null($notification->read_at))
                                    <span class="w-2 h-2 bg-[var(--color-red)] rounded-full flex-shrink-0"></span>
                                @endif
                            </div>
                            <p class="text-sm text-[var(--color-steel)] mt-1">{{ $message }}</p>
                            <p class="text-xs text-[var(--color-steel)] mt-1">{{ $time }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if(is_null($notification->read_at))
                                <form method="POST" action="{{ route('admin.notifications.read', $notification) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-[var(--color-steel)] hover:text-[var(--color-red)] text-sm" title="Mark as Read">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.notifications.destroy', $notification) }}" class="inline" data-confirm="Delete this notification?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-[var(--color-steel)] hover:text-[var(--color-red)] text-sm" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-[var(--color-steel)]">
                    <i class="fa-solid fa-bell-slash text-3xl text-[var(--color-steel)] mb-2"></i>
                    <p>No notifications found.</p>
                </div>
            @endforelse
        </div>
    </div>

    @if($notifications->hasPages())
        <div class="p-4 border-t">{{ $notifications->links() }}</div>
    @endif
</form>

<form method="POST" action="{{ route('admin.notifications.bulk-delete') }}" id="bulk-form-delete" class="hidden">
    @csrf
    <div id="bulk-delete-inputs"></div>
</form>

@push('scripts')
<script nonce="{{ $cspNonce }}">
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.notification-checkbox');
    const selectedCount = document.getElementById('selected-count');
    const bulkReadSubmit = document.getElementById('bulk-read-submit');
    const bulkDeleteSubmit = document.getElementById('bulk-delete-submit');

    function updateCount() {
        const checked = document.querySelectorAll('.notification-checkbox:checked');
        selectedCount.textContent = checked.length + ' selected';
        bulkReadSubmit.disabled = checked.length === 0;
        bulkDeleteSubmit.disabled = checked.length === 0;
    }

    selectAll.addEventListener('change', () => {
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        updateCount();
    });

    checkboxes.forEach(cb => cb.addEventListener('change', updateCount));

    document.getElementById('bulk-form-read').addEventListener('submit', function() {
        const checked = document.querySelectorAll('.notification-checkbox:checked');
        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'notification_ids[]';
            input.value = cb.value;
            this.appendChild(input);
        });
    });

    bulkDeleteSubmit.addEventListener('click', function() {
        const checked = document.querySelectorAll('.notification-checkbox:checked');
        const container = document.getElementById('bulk-delete-inputs');
        container.innerHTML = '';
        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'notification_ids[]';
            input.value = cb.value;
            container.appendChild(input);
        });
        document.getElementById('bulk-form-delete').submit();
    });
</script>
@endpush
@endsection
