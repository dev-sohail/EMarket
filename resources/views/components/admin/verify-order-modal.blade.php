<div id="verify-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/40" data-action="closeVerifyModal"></div>
    <div class="relative bg-[var(--color-paper)] rounded-lg border hairline w-full max-w-lg shadow-xl">
        <div class="flex items-center justify-between p-4 border-b hairline">
            <h3 class="font-semibold text-[var(--color-ink)]">Verify Order #<span id="verify-order-number"></span></h3>
            <button data-action="closeVerifyModal" class="text-[var(--color-steel)] hover:text-[var(--color-ink)]"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="p-4">
            <div id="verify-loading" class="text-center py-8">
                <i class="fa-solid fa-spinner fa-spin text-2xl text-[var(--color-steel)]"></i>
            </div>
            <div id="verify-content" class="hidden">
                <div class="space-y-3">
                    <div class="flex items-center gap-2 text-sm">
                        <i id="verify-payment-icon"></i>
                        <span class="text-[var(--color-ink)]">Payment Status:</span>
                        <span id="verify-payment-status" class="font-medium"></span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i id="verify-shipping-icon"></i>
                        <span class="text-[var(--color-ink)]">Shipping Address:</span>
                        <span id="verify-shipping-status"></span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i id="verify-billing-icon"></i>
                        <span class="text-[var(--color-ink)]">Billing Address:</span>
                        <span id="verify-billing-status"></span>
                    </div>
                </div>
                <div id="verify-issues" class="mt-4 p-3 bg-[var(--color-red)]/5 border border-[var(--color-red)]/20 rounded hidden">
                    <p class="text-sm font-medium text-[var(--color-red)] mb-1">Issues Found:</p>
                    <ul id="verify-issues-list" class="list-disc list-inside text-sm text-[var(--color-red)] space-y-1"></ul>
                </div>
                <div id="verify-success" class="mt-4 p-3 bg-[#1B7A43]/5 border border-[#1B7A43]/20 rounded hidden">
                    <p class="text-sm font-medium text-[#1B7A43]">Order is verified and ready for processing.</p>
                </div>
            </div>
        </div>
        <div class="p-4 border-t hairline flex justify-end">
            <button data-action="closeVerifyModal" class="border hairline px-4 py-2 rounded text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Close</button>
        </div>
    </div>
</div>

<script nonce="{{ $cspNonce }}">
    function openVerifyModal(id, number) {
        const modal = document.getElementById('verify-modal');
        const orderNumberEl = document.getElementById('verify-order-number');
        const loadingEl = document.getElementById('verify-loading');
        const contentEl = document.getElementById('verify-content');

        orderNumberEl.textContent = number;
        modal.classList.remove('hidden');
        loadingEl.classList.remove('hidden');
        contentEl.classList.add('hidden');

        fetch(`/admin/order-processor/${id}/verify`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            loadingEl.classList.add('hidden');
            contentEl.classList.remove('hidden');

            const paymentIcon = document.getElementById('verify-payment-icon');
            const paymentStatus = document.getElementById('verify-payment-status');
            paymentIcon.className = data.payment_status === 'paid' ? 'fa-solid fa-circle-check text-[#1B7A43]' : 'fa-solid fa-circle-xmark text-[var(--color-red)]';
            paymentStatus.className = 'font-medium ' + (data.payment_status === 'paid' ? 'text-[#1B7A43]' : 'text-[var(--color-red)]');
            paymentStatus.textContent = data.payment_status;

            const shippingIcon = document.getElementById('verify-shipping-icon');
            const shippingStatus = document.getElementById('verify-shipping-status');
            shippingIcon.className = data.shipping_address_valid ? 'fa-solid fa-circle-check text-[#1B7A43]' : 'fa-solid fa-circle-xmark text-[var(--color-red)]';
            shippingStatus.className = data.shipping_address_valid ? 'text-[#1B7A43]' : 'text-[var(--color-red)]';
            shippingStatus.textContent = data.shipping_address_valid ? 'Valid' : 'Incomplete';

            const billingIcon = document.getElementById('verify-billing-icon');
            const billingStatus = document.getElementById('verify-billing-status');
            billingIcon.className = data.billing_address_valid ? 'fa-solid fa-circle-check text-[#1B7A43]' : 'fa-solid fa-circle-xmark text-[var(--color-red)]';
            billingStatus.className = data.billing_address_valid ? 'text-[#1B7A43]' : 'text-[var(--color-red)]';
            billingStatus.textContent = data.billing_address_valid ? 'Valid' : 'Incomplete';

            const issuesEl = document.getElementById('verify-issues');
            const issuesList = document.getElementById('verify-issues-list');
            if (data.issues && data.issues.length) {
                issuesEl.classList.remove('hidden');
                // Build nodes with textContent: the issue strings embed
                // product names, so innerHTML would let a crafted product name
                // execute script inside the admin panel.
                issuesList.replaceChildren(...data.issues.map(issue => {
                    const li = document.createElement('li');
                    li.textContent = issue;
                    return li;
                }));
            } else {
                issuesEl.classList.add('hidden');
            }

            const successEl = document.getElementById('verify-success');
            if (data.verified) {
                successEl.classList.remove('hidden');
            } else {
                successEl.classList.add('hidden');
            }
        })
        .catch(() => {
            loadingEl.classList.add('hidden');
            contentEl.classList.remove('hidden');
        });
    }

    function closeVerifyModal() {
        document.getElementById('verify-modal').classList.add('hidden');
    }
</script>
