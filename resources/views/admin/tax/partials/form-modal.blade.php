@php
    $editing = old('id') !== null;
@endphp

<div id="tax-rule-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="tax-rule-modal-title">
    <div class="fixed inset-0 bg-black/40" data-action="closeTaxRuleModal"></div>

    <div class="relative bg-[var(--color-paper)] rounded-lg border hairline w-full max-w-md shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b hairline sticky top-0 bg-[var(--color-paper)]">
            <h3 id="tax-rule-modal-title" class="font-semibold text-[var(--color-ink)]">{{ $editing ? 'Edit Tax Rule' : 'Add Tax Rule' }}</h3>
            <button type="button" data-action="closeTaxRuleModal" class="text-[var(--color-steel)] hover:text-[var(--color-ink)]" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="tax-rule-form" method="POST" action="{{ route('admin.tax.store') }}" class="p-4 space-y-4">
            @csrf
            <input type="hidden" name="id" id="tax-rule-id" value="{{ old('id') }}">

            <div>
                <label for="tax-rule-name" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Name</label>
                <input type="text" name="name" id="tax-rule-name" value="{{ old('name') }}" required
                       placeholder="e.g. VAT" maxlength="255"
                       class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                @error('name') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="tax-rule-rate" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Rate</label>
                    <input type="number" name="rate" id="tax-rule-rate" value="{{ old('rate') }}" step="0.01" min="0" max="100" required
                           class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                    <p class="mt-1 text-xs text-[var(--color-steel)]">Percentage value, or fixed amount.</p>
                    @error('rate') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="tax-rule-type" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Type</label>
                    <select name="type" id="tax-rule-type" class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                        <option value="percentage" @selected(old('type', 'percentage') === 'percentage')>Percentage</option>
                        <option value="fixed" @selected(old('type') === 'fixed')>Fixed</option>
                    </select>
                    @error('type') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
            </div>

            <label class="flex items-center justify-between p-3 border hairline rounded-lg hover:bg-[var(--color-paper-dim)] cursor-pointer transition-colors">
                <div>
                    <span class="text-sm font-medium text-[var(--color-ink-soft)]">Active</span>
                    <p class="text-xs text-[var(--color-steel)]">Inactive rules are skipped at checkout.</p>
                </div>
                <input type="checkbox" name="is_active" value="1" id="tax-rule-active" @checked(old('is_active', true))
                       class="w-5 h-5 rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-2 focus:ring-[var(--color-red)] focus:ring-offset-0">
            </label>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" data-action="closeTaxRuleModal" class="border hairline px-4 py-2 rounded-lg text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Cancel</button>
                <button type="submit" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center">
                    <i class="fa-solid fa-check mr-2"></i> <span id="tax-rule-submit-label">{{ $editing ? 'Save Changes' : 'Add Tax Rule' }}</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script nonce="{{ $cspNonce }}">
    // Add / edit share one modal: no arguments means "create", otherwise the
    // row values come through `data-args` on the row's Edit button.
    function openTaxRuleModal(id = null, name = '', rate = '', type = 'percentage', isActive = 1) {
        const modal = document.getElementById('tax-rule-modal');
        const editing = Boolean(id);

        document.getElementById('tax-rule-id').value = id ?? '';
        document.getElementById('tax-rule-name').value = name;
        document.getElementById('tax-rule-rate').value = rate;
        document.getElementById('tax-rule-type').value = type;
        document.getElementById('tax-rule-active').checked = Boolean(Number(isActive));
        document.getElementById('tax-rule-modal-title').textContent = editing ? 'Edit Tax Rule' : 'Add Tax Rule';
        document.getElementById('tax-rule-submit-label').textContent = editing ? 'Save Changes' : 'Add Tax Rule';

        modal.classList.remove('hidden');
        document.getElementById('tax-rule-name').focus();
    }

    function closeTaxRuleModal() {
        document.getElementById('tax-rule-modal').classList.add('hidden');
    }

    // A failed validation redirect comes back with the modal fields pre-filled,
    // so reopen it instead of silently dropping the user on an empty page.
    @if($errors->any())
        window.addEventListener('DOMContentLoaded', function () {
            openTaxRuleModal(
                @js(old('id')),
                @js(old('name', '')),
                @js(old('rate', '')),
                @js(old('type', 'percentage')),
                @js(old('is_active') === null ? 0 : 1)
            );
        });
    @endif
</script>