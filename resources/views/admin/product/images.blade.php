@extends('layouts.admin')

@section('title', 'Manage Product Images')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <nav class="flex items-center gap-2 text-xs text-[var(--color-steel)] mb-1">
            <a href="{{ route('admin.products.index') }}" class="hover:text-[var(--color-ink)] transition">Products</a>
            <i class="fa-solid fa-chevron-right text-[0.6rem]"></i>
            <span class="text-[var(--color-ink-soft)]">{{ $product->name }}</span>
        </nav>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Images</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">
            {{ $product->images->count() }} image{{ $product->images->count() === 1 ? '' : 's' }} uploaded
            @if($product->images->firstWhere('is_primary', true))
                &middot; the primary image is used as the catalogue thumbnail.
            @endif
        </p>
    </div>
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.products.edit', $product->getKey()) }}" class="px-4 py-2 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
            Back to Product
        </a>
        <a href="{{ route('admin.products.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)] transition">All Products</a>
    </div>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.products.images.store', $product->getKey()) }}" enctype="multipart/form-data" class="bg-[var(--color-paper)] rounded-lg border hairline p-6 mb-6">
    @csrf
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-semibold text-[var(--color-ink)]">Upload Images</h2>
        <span class="text-xs text-[var(--color-steel)]">JPEG, PNG, JPG, GIF, WebP &middot; max 5 MB each</span>
    </div>

    <div id="upload-zone" class="border-2 border-dashed border-[var(--color-line)] rounded-lg p-8 text-center cursor-pointer hover:border-[var(--color-red)] transition relative">
        <input type="file" name="images[]" multiple accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" id="file-input">
        <div id="upload-placeholder">
            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                <i class="fa-solid fa-cloud-arrow-up text-lg text-[var(--color-steel)]"></i>
            </div>
            <p class="text-sm text-[var(--color-ink)] font-medium">Drag &amp; drop images here, or click to browse</p>
            <p class="text-xs text-[var(--color-steel)] mt-1">Recommended size 800 &times; 800 px</p>
        </div>
        <div id="upload-preview" class="hidden">
            <div class="grid grid-cols-4 sm:grid-cols-6 gap-2" id="preview-grid"></div>
            <p class="text-sm text-[var(--color-steel)] mt-3"><span id="file-count">0</span> file(s) selected</p>
        </div>
    </div>
    @error('images') <p class="mt-2 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
    @error('images.*') <p class="mt-2 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror

    <div class="flex items-center justify-between mt-4">
        <button type="button" id="clear-files" class="hidden px-4 py-2 bg-[var(--color-paper-dim)] text-[var(--color-ink)] text-sm font-medium rounded-lg hover:bg-[var(--color-line)] transition">Clear</button>
        <button type="submit" id="upload-btn" class="ml-auto bg-[var(--color-red)] text-white py-2.5 px-6 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>Upload Images</button>
    </div>
</form>

<div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h2 class="font-semibold text-[var(--color-ink)]">Current Images</h2>

        <div class="flex items-center gap-3">
            <button type="button" data-action="selectAllImages" class="text-xs text-[var(--color-steel)] hover:text-[var(--color-ink)] transition">Select all</button>
            {{-- The checkboxes below live outside this form (each row has its
                 own Set Primary / Delete forms, which cannot nest), so they
                 carry `form="bulk-delete-form"` to associate themselves with
                 it. The endpoint validates `image_ids` as an array, which is
                 why the name ends in `[]`. The previous single `image_ids`
                 hidden field could only carry a JSON string, so the request
                 failed validation and deleted nothing. --}}
            <form method="POST" action="{{ route('admin.products.images.bulk-delete', $product->getKey()) }}" id="bulk-delete-form" data-confirm="Delete the selected images? This cannot be undone." class="hidden">
                @csrf
                @method('DELETE')
            </form>
            <button type="submit" form="bulk-delete-form" id="bulk-delete-btn" class="hidden px-4 py-2 bg-[var(--color-red)] text-white text-sm font-medium rounded-lg hover:bg-[var(--color-red-dark)] transition">Delete Selected</button>
        </div>
    </div>

    <div id="sortable-grid" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        @forelse($product->images ?? [] as $img)
            <div class="relative border hairline rounded-lg overflow-hidden group" data-id="{{ $img->getKey() }}">
                <div class="relative">
                    <img src="{{ asset('storage/' . $img->path) }}" class="w-full h-48 object-cover cursor-move" alt="{{ $img->alt_text }}">
                    <div class="absolute top-2 left-2">
                        <input type="checkbox" class="image-checkbox rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]" name="image_ids[]" form="bulk-delete-form" value="{{ $img->getKey() }}" aria-label="Select image">
                    </div>
                    @if($img->is_primary)
                        <span class="absolute top-2 right-2 bg-[var(--color-red)] text-white text-xs px-2 py-1 rounded font-medium">Primary</span>
                    @endif
                </div>
                <div class="p-3 bg-[var(--color-paper)]">
                    <p class="text-xs text-[var(--color-steel)] truncate">{{ $img->alt_text ?: 'No alt text' }}</p>
                    <div class="flex gap-2 mt-2">
                        @if(!$img->is_primary)
                            <form method="POST" action="{{ route('admin.products.images.primary', [$product->getKey(), $img->getKey()]) }}" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full text-xs bg-[var(--color-paper-dim)] text-[var(--color-ink)] px-2 py-1.5 rounded hover:bg-[var(--color-line)] transition">Set Primary</button>
                            </form>
                        @else
                            <span class="flex-1 text-center text-xs text-[var(--color-steel)] py-1.5">In use as thumbnail</span>
                        @endif
                        <form method="POST" action="{{ route('admin.products.images.destroy', [$product->getKey(), $img->getKey()]) }}" data-confirm="Delete this image?" class="{{ $img->is_primary ? 'w-full' : 'flex-1' }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full text-xs text-[var(--color-red)] hover:text-[var(--color-red-dark)] px-2 py-1.5 rounded hover:bg-[var(--color-red)]/5 transition">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                    <i class="fa-regular fa-images text-[var(--color-steel)]"></i>
                </div>
                <p class="text-sm font-medium text-[var(--color-ink)]">No images uploaded yet</p>
                <p class="text-sm text-[var(--color-steel)] mt-1">Upload at least one image above so this product has a thumbnail in the catalogue.</p>
            </div>
        @endforelse
    </div>

    @if($product->images->count() > 0)
        <div class="mt-4 p-3 bg-[var(--color-paper-dim)] rounded-lg text-xs text-[var(--color-steel)]">
            <i class="fa-solid fa-circle-info mr-1"></i> Drag images to reorder. The first image is the default; the primary badge marks the catalogue thumbnail.
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('file-input');
    const uploadZone = document.getElementById('upload-zone');
    const previewGrid = document.getElementById('preview-grid');
    const uploadPlaceholder = document.getElementById('upload-placeholder');
    const uploadPreview = document.getElementById('upload-preview');
    const fileCount = document.getElementById('file-count');
    const clearBtn = document.getElementById('clear-files');
    const uploadBtn = document.getElementById('upload-btn');

    if (fileInput) {
        let selectedFiles = [];

        fileInput.addEventListener('change', function(e) {
            selectedFiles = Array.from(e.target.files);
            updatePreview();
        });

        uploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadZone.classList.add('border-[var(--color-red)]', 'bg-[var(--color-red)]/5');
        });

        uploadZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadZone.classList.remove('border-[var(--color-red)]', 'bg-[var(--color-red)]/5');
        });

        uploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadZone.classList.remove('border-[var(--color-red)]', 'bg-[var(--color-red)]/5');
            selectedFiles = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/'));
            updatePreview();
        });

        function updatePreview() {
            if (selectedFiles.length > 0) {
                uploadPlaceholder.classList.add('hidden');
                uploadPreview.classList.remove('hidden');
                clearBtn.classList.remove('hidden');
                uploadBtn.disabled = false;
                fileCount.textContent = selectedFiles.length;
                previewGrid.innerHTML = '';
                selectedFiles.forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'relative';
                        div.innerHTML = '<img src="' + e.target.result + '" class="w-full h-24 object-cover rounded border hairline">';
                        previewGrid.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });
            } else {
                uploadPlaceholder.classList.remove('hidden');
                uploadPreview.classList.add('hidden');
                clearBtn.classList.add('hidden');
                uploadBtn.disabled = true;
                fileInput.value = '';
            }
        }

        clearBtn.addEventListener('click', function() {
            selectedFiles = [];
            updatePreview();
        });
    }

    const checkboxes = document.querySelectorAll('.image-checkbox');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

    function checkedBoxes() {
        return Array.from(document.querySelectorAll('.image-checkbox:checked'));
    }

    function updateBulkDelete() {
        bulkDeleteBtn.classList.toggle('hidden', checkedBoxes().length === 0);
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateBulkDelete));

    const sortableGrid = document.getElementById('sortable-grid');

    if (sortableGrid) {
        let draggedItem = null;

        sortableGrid.querySelectorAll('.group').forEach(item => {
            item.setAttribute('draggable', 'true');
        });

        sortableGrid.addEventListener('dragstart', function(e) {
            const item = e.target.closest('.group');
            if (!item) return;

            draggedItem = item;
            item.classList.add('opacity-40');
        });

        sortableGrid.addEventListener('dragend', function() {
            if (!draggedItem) return;

            draggedItem.classList.remove('opacity-40');
            draggedItem = null;
            saveOrder();
        });

        sortableGrid.addEventListener('dragover', function(e) {
            e.preventDefault();
            if (!draggedItem) return;

            const afterElement = getDragAfterElement(sortableGrid, e.clientY);

            if (afterElement == null) {
                sortableGrid.appendChild(draggedItem);
            } else {
                sortableGrid.insertBefore(draggedItem, afterElement);
            }
        });

        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.group:not(.opacity-40)')];

            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;

                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                }

                return closest;
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        function saveOrder() {
            const ids = Array.from(sortableGrid.querySelectorAll('.group')).map(item => item.dataset.id);

            fetch('{{ route('admin.products.images.reorder', $product->getKey()) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ images: ids })
            }).then(response => {
                if (!response.ok) throw new Error('reorder failed');
                showToast('Image order saved.');
            }).catch(() => {
                showToast('Could not save the new order. Reload and try again.', 'error');
            });
        }
    }
});

/**
 * Check/uncheck every image row at once. Without it, bulk delete means
 * clicking each checkbox by hand before the button appears.
 */
function selectAllImages() {
    const boxes = Array.from(document.querySelectorAll('.image-checkbox'));
    const shouldCheck = boxes.some(box => !box.checked);

    boxes.forEach(box => {
        box.checked = shouldCheck;
        box.dispatchEvent(new Event('change', { bubbles: true }));
    });
}
</script>
@endpush