{{--
    Behaviour shared by the product create and edit forms.

    The variant row markup lives in a <template> rather than a JS string so the
    server-rendered rows and the dynamically added ones cannot drift, and so
    the attribute-value picker is generated once from the same loop that renders
    the initial rows.
--}}
<script nonce="{{ $cspNonce }}">
(function () {
    /**
     * Build the next array index for a variants[] payload.
     *
     * Using `container.children.length` reuses an index as soon as a row is
     * removed: with rows 0 and 1 present, removing row 0 leaves one child, so
     * the next row is indexed 1 and both rows submit as `variants[1]`, which
     * silently drops one variant. Reading the highest existing index instead
     * keeps every row on its own key.
     */
    function nextVariantIndex(container) {
        let highest = -1;

        container.querySelectorAll('[data-variant-index]').forEach(row => {
            const index = parseInt(row.dataset.variantIndex, 10);

            if (!isNaN(index) && index > highest) {
                highest = index;
            }
        });

        return highest + 1;
    }

    function addVariant() {
        const container = document.getElementById('variants-container');

        if (!container) return;

        const template = document.getElementById('variant-row-template');

        if (!template) return;

        const index = nextVariantIndex(container);
        const html = template.innerHTML.replace(/__INDEX__/g, index);
        const holder = document.createElement('div');

        holder.innerHTML = html.trim();

        const row = holder.firstElementChild;

        if (!row) return;

        container.appendChild(row);
        row.dataset.variantIndex = index;

        document.getElementById('no-variants-msg')?.classList.add('hidden');
    }

    function removeVariant() {
        // Invoked through the delegated `data-action` dispatcher, which applies
        // the handler with the clicked element as `this`; there is no `button`
        // argument, so the old parameter was undefined and `closest` threw.
        const button = this;
        // Walk up to the row this button lives in. The previous markup put the
        // button outside the [data-removable] wrapper, so `remove-self` found
        // no ancestor and the click did nothing.
        const row = button.closest('[data-removable]');

        if (!row) return;

        row.remove();

        if (!document.querySelector('#variants-container [data-removable]')) {
            document.getElementById('no-variants-msg')?.classList.remove('hidden');
        }
    }

    /**
     * Delete the product from the edit screen without nesting a form inside
     * the edit form, which is invalid HTML and would submit the product's own
     * fields to the delete endpoint.
     */
    function deleteProduct(url, name) {
        if (!window.confirm('Delete "' + name + '"? This cannot be undone.')) {
            return;
        }

        const form = document.createElement('form');

        form.method = 'POST';
        form.action = url;
        form.innerHTML = '<input type="hidden" name="_token" value="' +
            document.querySelector('meta[name="csrf-token"]').content + '">' +
            '<input type="hidden" name="_method" value="DELETE">';

        document.body.appendChild(form);
        form.submit();
    }

    window.addVariant = addVariant;
    window.removeVariant = removeVariant;
    window.deleteProduct = deleteProduct;

    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('variants-container')) {
            // Tag the server-rendered rows so the index scan has a baseline to
            // work from.
            document.querySelectorAll('#variants-container > [data-removable]').forEach((row, index) => {
                row.dataset.variantIndex = index;
            });
        }
    });
})();
</script>

<script src="{{ asset('vendor/ckeditor/ckeditor.js') }}"></script>
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function () {
    // The description is loaded on every product screen but was never wired up,
    // so the rich-text editor never appeared.
    //
    // ckeditorSync() is defined once in layouts/admin.blade.php and copies the
    // editor's content back into the textarea, so a typed description actually
    // reaches the POST instead of failing as "required".
    const field = document.getElementById('description');

    if (field && window.CKEDITOR) {
        window.ckeditorSync(CKEDITOR.replace(field));
    }
});
</script>