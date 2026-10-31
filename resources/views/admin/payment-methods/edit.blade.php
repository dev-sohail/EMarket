@extends('layouts.admin')

@section('title', 'Edit Payment Method')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">{{ $paymentMethod->name }}</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">
            <span class="inline-flex items-center rounded-md bg-[var(--color-paper-dim)] px-2 py-0.5 font-mono text-xs text-[var(--color-ink-soft)]">{{ $paymentMethod->code }}</span>
            <span class="ml-2">{{ $paymentMethod->is_cod ? 'Cash on Delivery' : 'Online' }} · storefront position {{ $paymentMethod->sort_order }}</span>
        </p>
    </div>
    <a href="{{ route('admin.payment-methods.index') }}" class="px-4 py-2 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition flex items-center gap-2">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Methods
    </a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.payment-methods.update', $paymentMethod->getKey()) }}" class="max-w-3xl space-y-4">
    @csrf
    @method('PUT')

    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <div class="flex items-start gap-3 mb-6 pb-5 border-b hairline">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 {{ $paymentMethod->is_cod ? 'bg-[var(--color-red)]/10 text-[var(--color-red-dark)]' : 'bg-[#635BFF]/10 text-[#635BFF]' }}">
                <i class="fa-solid {{ $paymentMethod->is_cod ? 'fa-money-bill-wave' : 'fa-globe' }}"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-[var(--color-ink)]">Checkout behaviour</p>
                <p class="text-sm text-[var(--color-steel)] mt-0.5">
                    @if($paymentMethod->is_cod)
                        Customers pay in cash when the order is delivered. The instructions below are shown on the confirmation screen.
                    @else
                        Records the customer's payment selection and instructions. Card details are collected on Stripe's hosted page — this method never stores raw card data.
                    @endif
                </p>
            </div>
        </div>

        <div class="space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Name <span class="text-[var(--color-red)]">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name', $paymentMethod->name) }}" required
                       class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('name') border-[var(--color-red)] @enderror">
                <p class="text-xs text-[var(--color-steel)] mt-1">Shown to customers at checkout. Changing it does not alter the <span class="font-mono">{{ $paymentMethod->code }}</span> code.</p>
                @error('name') <p class="text-xs text-[var(--color-red)] mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Customer Instructions</label>
                <textarea name="description" id="description" rows="4" maxlength="1000"
                          class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('description') border-[var(--color-red)] @enderror">{{ old('description', $paymentMethod->description ?? '') }}</textarea>
                <p class="text-xs text-[var(--color-steel)] mt-1">Leave blank to show no instructions. Bank transfer details belong here for the Online method.</p>
                @error('description') <p class="text-xs text-[var(--color-red)] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="sort_order" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Storefront Position</label>
                    <input type="number" name="sort_order" id="sort_order" min="0" step="1" value="{{ old('sort_order', $paymentMethod->sort_order) }}"
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('sort_order') border-[var(--color-red)] @enderror">
                    <p class="text-xs text-[var(--color-steel)] mt-1">Lower numbers appear first at checkout.</p>
                    @error('sort_order') <p class="text-xs text-[var(--color-red)] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <span class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Availability</span>
                    <input type="hidden" name="is_active" value="0">
                    <label class="inline-flex items-center gap-2 px-3 py-2.5 w-full border hairline rounded-lg cursor-pointer hover:bg-[var(--color-paper-dim)] transition has-checked:border-[var(--color-red)]">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $paymentMethod->is_active) ? 'checked' : '' }}
                               class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                        <span class="text-sm text-[var(--color-ink)]">
                            {{ old('is_active', $paymentMethod->is_active) ? 'Active — offered at checkout' : 'Inactive — hidden from checkout' }}
                        </span>
                    </label>
                    @error('is_active') <p class="text-xs text-[var(--color-red)] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>

    @unless($paymentMethod->is_cod)
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-start gap-3 mb-6 pb-5 border-b hairline">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-[#635BFF]/10 text-[#635BFF]">
                    <i class="fa-brands fa-stripe"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-[var(--color-ink)]">Stripe Gateway</p>
                    <p class="text-sm text-[var(--color-steel)] mt-0.5">Keys must match the environment below — <span class="font-mono">pk_test_</span> with Sandbox on, <span class="font-mono">pk_live_</span> with it off.</p>
                </div>
            </div>

            <div class="space-y-5">
                <div>
                    <input type="hidden" name="stripe_enabled" value="0">
                    <label class="inline-flex items-start gap-2 px-3 py-2.5 w-full border hairline rounded-lg cursor-pointer hover:bg-[var(--color-paper-dim)] transition has-checked:border-[var(--color-red)]">
                        <input type="checkbox" name="stripe_enabled" value="1" {{ old('stripe_enabled', $paymentMethod->stripe_enabled) ? 'checked' : '' }}
                               class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)] mt-0.5">
                        <span>
                            <span class="block text-sm font-medium text-[var(--color-ink)]">Process cards with Stripe</span>
                            <span class="block text-xs text-[var(--color-steel)] mt-0.5">When off, this method collects no card data and customers follow the instructions above instead.</span>
                        </span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="stripe_publishable_key" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Publishable Key</label>
                        <input type="text" name="stripe_publishable_key" id="stripe_publishable_key" autocomplete="off"
                               value="{{ old('stripe_publishable_key', $paymentMethod->stripe_publishable_key ?? '') }}"
                               class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 font-mono text-sm focus:ring-2 focus:ring-[var(--color-red)] @error('stripe_publishable_key') border-[var(--color-red)] @enderror"
                               placeholder="pk_live_… or pk_test_…">
                        <p class="text-xs text-[var(--color-steel)] mt-1">Safe to expose in the browser.</p>
                        @error('stripe_publishable_key') <p class="text-xs text-[var(--color-red)] mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="stripe_secret_key" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Secret Key</label>
                        {{-- Never echoed back: the column is an encrypted cast, so re-rendering
                             the decrypted value would leak it into the page source and any log
                             that captures markup. A blank field keeps the stored key. --}}
                        <input type="password" name="stripe_secret_key" id="stripe_secret_key" autocomplete="new-password" value=""
                               class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 font-mono text-sm focus:ring-2 focus:ring-[var(--color-red)] @error('stripe_secret_key') border-[var(--color-red)] @enderror"
                               placeholder="{{ $paymentMethod->stripe_secret_key ? '•••••••••••• saved — type to replace' : 'sk_live_… or sk_test_…' }}">
                        <p class="text-xs text-[var(--color-steel)] mt-1">
                            @if($paymentMethod->stripe_secret_key)
                                A key is already saved. Leave blank to keep it.
                            @else
                                No key saved yet.
                            @endif
                        </p>
                        @error('stripe_secret_key') <p class="text-xs text-[var(--color-red)] mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <input type="hidden" name="stripe_sandbox" value="0">
                    <label class="inline-flex items-center gap-2 px-3 py-2.5 w-full border hairline rounded-lg cursor-pointer hover:bg-[var(--color-paper-dim)] transition has-checked:border-[var(--color-red)]">
                        <input type="checkbox" name="stripe_sandbox" value="1" {{ old('stripe_sandbox', $paymentMethod->stripe_sandbox) ? 'checked' : '' }}
                               class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                        <span class="text-sm text-[var(--color-ink)]">
                            Sandbox (test) mode — use <span class="font-mono">pk_test_</span> / <span class="font-mono">sk_test_</span> keys and never charge a real card.
                        </span>
                    </label>
                    @error('stripe_sandbox') <p class="text-xs text-[var(--color-red)] mt-1">{{ $message }}</p> @enderror
                </div>

                @error('stripe_keys')
                    <div class="rounded-lg border border-[var(--color-red)] bg-[var(--color-red)]/5 p-4">
                        <p class="text-sm font-medium text-[var(--color-red-dark)] flex items-center gap-2">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            Stripe configuration is incomplete
                        </p>
                        <ul class="mt-2 space-y-1">
                            @foreach($errors->get('stripe_keys') as $message)
                                <li class="text-sm text-[var(--color-steel)]">{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @enderror
            </div>
        </div>
    @endunless

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="px-5 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2">
            <i class="fa-solid fa-check"></i>
            Save Changes
        </button>
        <a href="{{ route('admin.payment-methods.index') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Cancel</a>
    </div>
</form>
@endsection
