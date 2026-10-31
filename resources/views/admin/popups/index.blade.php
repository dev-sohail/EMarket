@extends('layouts.admin')

@section('title', 'Popups')

@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Popups</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Manage storefront popups and modal overlays</p>
    </div>
    <a href="{{ route('admin.popups.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Add Popup
    </a>
</div>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Toolbar -->
    <div class="bg-[var(--color-paper)] rounded-lg border p-4">
        <div class="flex items-center gap-4">
            <div class="relative flex-1 max-w-md">
                <svg class="w-4 h-4 text-[var(--color-steel)] absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="popup-search" placeholder="Search by name, title, or type..." class="w-full pl-10 pr-4 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>
            <div class="text-sm text-[var(--color-steel)]">
                <span id="popup-count">{{ $popups->count() }}</span> popups
            </div>
        </div>
    </div>

    <!-- Card Grid -->
    <div id="popup-grid" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($popups as $popup)
        <div class="popup-card bg-[var(--color-paper)] rounded-lg border overflow-hidden cursor-move"
             data-popup-id="{{ $popup->getKey() }}"
             data-popup-name="{{ e($popup->name) }}"
             data-popup-title="{{ e($popup->title ?? '') }}"
             data-popup-type="{{ $popup->type }}"
             data-popup-position="{{ $popup->position }}"
             data-popup-size="{{ $popup->size }}"
             data-popup-color="{{ $popup->color ?? '#D6001C' }}"
             data-popup-overlay-color="{{ $popup->overlay_color ?? '#000000' }}"
             data-popup-image="{{ $popup->image ? asset('storage/' . $popup->image) : '' }}"
             data-popup-cta-text="{{ e($popup->cta_text ?? '') }}"
             data-popup-url="{{ e($popup->url ?? '') }}"
             data-popup-is-active="{{ $popup->is_active ? '1' : '0' }}"
             data-popup-delay-seconds="{{ $popup->delay_seconds ?? 0 }}"
             data-popup-enable-cookie="{{ $popup->enable_cookie ? '1' : '0' }}"
             data-popup-show-only-once="{{ $popup->show_only_once ? '1' : '0' }}"
             data-popup-cookie-expiry-days="{{ $popup->cookie_expiry_days ?? 7 }}"
             data-popup-subtitle="{{ e($popup->subtitle ?? '') }}"
             data-popup-description="{{ e($popup->description ?? '') }}"
             data-popup-custom-html="{{ e($popup->custom_html ?? '') }}"
             data-search="{{ strtolower($popup->name . ' ' . ($popup->title ?? '') . ' ' . $popup->type) }}">
            
            <div class="flex justify-between items-start p-4">
                <div>
                    <h3 class="font-semibold text-[var(--color-ink)] mb-2 truncate max-w-[120px]">{{ $popup->name }}</h3>
                    <p class="text-[var(--color-steel)] text-sm mb-2">{{ $popup->title ?? 'Untitled' }}</p>
                    <div class="flex items-center gap-2 text-[var(--color-steel)] text-xs">
                        <span class="px-2 py-0.5 rounded text-[var(--color-ink-soft)]">{{ ucfirst($popup->type) }}</span>
                        <span class="px-2 py-0.5 rounded text-[var(--color-ink-soft)]">{{ ucfirst($popup->size) }}</span>
                        <span class="px-2 py-0.5 rounded text-[var(--color-ink-soft)]">{{ ucfirst(str_replace('_', ' ', $popup->position)) }}</span>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    @if($popup->type === 'image' && $popup->image)
                        <div class="w-20 h-12 rounded border">
                            <img src="{{ asset('storage/' . $popup->image) }}" alt="Preview" class="w-full h-full object-cover">
                        </div>
                    @elseif($popup->type === 'solid_color')
                        <div class="w-20 h-12 rounded border" style="background-color: {{ $popup->color ?? '#D6001C' }}"></div>
                    @else
                        <div class="w-20 h-12 rounded border bg-[var(--color-paper-dim)] flex items-center justify-center">
                            <svg class="w-4 h-4 text-[var(--color-steel)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m-9 4a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10z"/></svg>
                        </div>
                    @endif
                </div>
            </div>
            
            <div class="border-t px-4 py-3">
                <div class="flex items-center justify-between text-[var(--color-steel)] text-sm">
                    <span>Status</span>
                    <div class="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" {{ $popup->is_active ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-indigo-500">
                    </div>
                </div>
            </div>
            
            <div class="px-4 py-3 bg-[var(--color-paper-dim)]">
                <div class="flex items-center justify-between gap-2 text-xs">
                    <div>
                        <span class="font-medium">Schedule</span>
                        <p class="mt-1">
                            @if($popup->starts_at || $popup->ends_at)
                                {{ $popup->starts_at?->format('Y-m-d') ?? 'Anytime' }} → {{ $popup->ends_at?->format('Y-m-d') ?? 'Ongoing' }}
                            @else
                                Ongoing
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.popups.edit', $popup->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11v-5m-1.414-9.414a2 2 0 012.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                        <form method="POST" action="{{ route('admin.popups.destroy', $popup->getKey()) }}" data-confirm="Delete this popup?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1 hover:text-[var(--color-red)] rounded-lg">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m-9-4h10"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Drag handle -->
            <div class="popup-drag-handle absolute top-2 right-2 p-1 text-[var(--color-steel)] hover:text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] rounded">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m-9 4a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2-2v10z"/></svg>
            </div>
        </div>
        @empty
        <div class="col-span-full py-12 text-center">
            <svg class="w-16 h-16 mx-auto mb-4 text-[var(--color-steel)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m-9 4a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2-2v10z"/></svg>
            <p class="text-[var(--color-steel)] mb-2">No popups found</p>
            <p class="text-[var(--color-steel)]/60 max-w-md mx-auto">Create your first popup to engage visitors with announcements, promotions, or important messages.</p>
            <a href="{{ route('admin.popups.create') }}" class="inline-flex items-center gap-2 bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Create First Popup
            </a>
        </div>
        @endforelse
    </div>
</div>

@push('scripts')
<!-- SortableJS CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script nonce="{{ $cspNonce }}">
// Search filter
document.getElementById('popup-search')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.popup-card').forEach(card => {
        card.style.display = card.dataset.search.includes(q) ? '' : 'none';
    });
    updateCount();
});

function updateCount() {
    const visible = document.querySelectorAll('.popup-card:not([style*="display: none"])').length;
    document.getElementById('popup-count').textContent = visible;
}

// Drag and drop reordering
(function() {
    const grid = document.getElementById('popup-grid');
    if (!grid || typeof Sortable === 'undefined') return;
    
    let dragStartIndex = null;
    
    Sortable.create(grid, {
        animation: 150,
        handle: '.popup-drag-handle',
        ghostClass: 'opacity-50',
        chosenClass: 'popup-card-dragging',
        dragClass: 'popup-card-dragging',
        onStart: function (evt) {
            dragStartIndex = Array.from(evt.item.parentElement.children).indexOf(evt.item);
            evt.item.style.zIndex = '1000';
        },
        onEnd: function (evt) {
            evt.item.style.zIndex = '';
            const dragEndIndex = Array.from(evt.item.parentElement.children).indexOf(evt.item);
            
            // Only send update if position changed
            if (dragStartIndex !== null && dragStartIndex !== dragEndIndex) {
                const ids = Array.from(grid.querySelectorAll('.popup-card')).map(card => card.dataset.popupId);
                
                fetch('{{ route("admin.popups.reorder") }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ popups: ids })
                }).then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to update popup order');
                    }
                    // Optional: show success toast
                }).catch(error => {
                    console.error('Reorder failed:', error);
                    // Optional: show error toast and revert
                    // For simplicity, we'll just log - in production you'd want to revert the UI
                });
            }
        }
    });
})();
</script>
@endpush

@endsection
