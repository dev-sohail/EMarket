@extends('layouts.store')

@section('title', 'Add Address')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8">
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('home') }}" class="hover:text-[var(--color-red)]">Home</a>
        <span>/</span>
        <a href="{{ route('customer.dashboard') }}" class="hover:text-[var(--color-red)]">Dashboard</a>
        <span>/</span>
        <a href="{{ route('customer.addresses') }}" class="hover:text-[var(--color-red)]">Addresses</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">Add New</span>
    </nav>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Add New Address</h1>
        <p class="text-[var(--color-steel)] mt-1">Enter the delivery details for this address.</p>
    </div>

    <div class="bg-white rounded-xl border hairline p-6 sm:p-8">
        <form method="POST" action="{{ route('customer.addresses.store') }}">
            @csrf

            {{-- Recipient Info --}}
            <fieldset class="mb-8">
                <legend class="text-sm font-semibold text-[var(--color-ink)] uppercase tracking-wider mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-user text-[var(--color-red)]"></i>
                    Recipient Information
                </legend>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-[var(--color-ink)] mb-1">First Name <span class="text-[var(--color-red)]">*</span></label>
                        <input type="text" name="first_name" id="first_name" value="{{ old('first_name', auth()->user()->name ? str(auth()->user()->name)->before(' ') : '') }}" class="w-full border hairline bg-white px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent @error('first_name') border-[var(--color-red)] @enderror" required autocomplete="given-name">
                        @error('first_name')
                            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="last_name" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Last Name <span class="text-[var(--color-red)]">*</span></label>
                        <input type="text" name="last_name" id="last_name" value="{{ old('last_name', auth()->user()->name ? str(auth()->user()->name)->after(' ') : '') }}" class="w-full border hairline bg-white px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent @error('last_name') border-[var(--color-red)] @enderror" required autocomplete="family-name">
                        @error('last_name')
                            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="email" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Email <span class="text-[var(--color-red)]">*</span></label>
                        <input type="email" name="email" id="email" value="{{ old('email', auth()->user()->email) }}" class="w-full border hairline bg-white px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent @error('email') border-[var(--color-red)] @enderror" required autocomplete="email">
                        @error('email')
                            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Phone <span class="text-[var(--color-red)]">*</span></label>
                        <input type="tel" name="phone" id="phone" value="{{ old('phone', auth()->user()->phone) }}" class="w-full border hairline bg-white px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent @error('phone') border-[var(--color-red)] @enderror" required autocomplete="tel" placeholder="+41 79 123 45 67">
                        @error('phone')
                            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-[var(--color-steel)]">For courier delivery updates</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="company" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Company (optional)</label>
                        <input type="text" name="company" id="company" value="{{ old('company') }}" class="w-full border hairline bg-white px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent" autocomplete="organization">
                    </div>
                </div>
            </fieldset>

            {{-- Address Details --}}
            <fieldset class="mb-8">
                <legend class="text-sm font-semibold text-[var(--color-ink)] uppercase tracking-wider mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-location-dot text-[var(--color-red)]"></i>
                    Delivery Address
                </legend>

                <div class="space-y-4">
                    <div>
                        <label for="address_line_1" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Street Address <span class="text-[var(--color-red)]">*</span></label>
                        <input type="text" name="address_line_1" id="address_line_1" value="{{ old('address_line_1') }}" class="w-full border hairline bg-white px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent @error('address_line_1') border-[var(--color-red)] @enderror" required autocomplete="street-address">
                        @error('address_line_1')
                            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="address_line_2" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Apartment, Suite, Unit (optional)</label>
                        <input type="text" name="address_line_2" id="address_line_2" value="{{ old('address_line_2') }}" class="w-full border hairline bg-white px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent" autocomplete="address-line2">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="city" class="block text-sm font-medium text-[var(--color-ink)] mb-1">City <span class="text-[var(--color-red)]">*</span></label>
                            <input type="text" name="city" id="city" value="{{ old('city') }}" class="w-full border hairline bg-white px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent @error('city') border-[var(--color-red)] @enderror" required autocomplete="address-level2">
                            @error('city')
                                <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="state" class="block text-sm font-medium text-[var(--color-ink)] mb-1">State / Canton <span class="text-[var(--color-red)]">*</span></label>
                            <input type="text" name="state" id="state" value="{{ old('state') }}" class="w-full border hairline bg-white px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent @error('state') border-[var(--color-red)] @enderror" required autocomplete="address-level1">
                            @error('state')
                                <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label for="postal_code" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Postal Code <span class="text-[var(--color-red)]">*</span></label>
                            <input type="text" name="postal_code" id="postal_code" value="{{ old('postal_code') }}" class="w-full border hairline bg-white px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent @error('postal_code') border-[var(--color-red)] @enderror" required autocomplete="postal-code">
                            @error('postal_code')
                                <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Country</label>
                            <div class="w-full px-4 py-2.5 rounded-lg bg-[var(--color-paper-dim)] border hairline text-[var(--color-ink)] font-mono text-sm uppercase">
                                {{ Str::upper(setting('default_country', 'CH')) }}
                            </div>
                            <input type="hidden" name="country" value="{{ Str::upper(setting('default_country', 'CH')) }}">
                            <p class="mt-1 text-xs text-[var(--color-steel)]">We only ship within {{ Str::upper(setting('default_country', 'CH')) }}</p>
                        </div>
                    </div>
                </div>
            </fieldset>

            {{-- Address Type --}}
            <fieldset class="mb-8">
                <legend class="text-sm font-semibold text-[var(--color-ink)] uppercase tracking-wider mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-tag text-[var(--color-red)]"></i>
                    Address Type
                </legend>

                <div class="grid grid-cols-2 gap-3">
                    @foreach(['home' => 'Home', 'work' => 'Work', 'other' => 'Other'] as $value => $label)
                        <label class="relative flex items-center justify-center p-4 border hairline rounded-lg cursor-pointer hover:bg-[var(--color-paper-dim)] transition has-[:checked]:border-[var(--color-red)] has-[:checked]:bg-[var(--color-red)]/5">
                            <input type="radio" name="type" value="{{ $value }}" {{ old('type', 'home') === $value ? 'checked' : '' }} class="sr-only peer">
                            <span class="text-sm font-medium text-[var(--color-ink)] peer-checked:text-[var(--color-red)]">{{ $label }}</span>
                            <i class="fa-solid fa-check text-[var(--color-red)] absolute opacity-0 peer-checked:opacity-100"></i>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- Default Address --}}
            <div class="mb-6 p-4 bg-[var(--color-paper)] rounded-lg border hairline">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }} class="w-5 h-5 rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-2 focus:ring-[var(--color-red)] focus:ring-offset-2">
                    <div>
                        <span class="text-sm font-medium text-[var(--color-ink)]">Set as default address</span>
                        <p class="text-xs text-[var(--color-steel)] mt-0.5">This address will be pre-selected at checkout</p>
                    </div>
                </label>
            </div>

            <div class="flex flex-col sm:flex-row justify-end gap-3 mt-8 pt-6 border-t hairline">
                <a href="{{ route('customer.addresses') }}" class="px-6 py-2.5 border hairline rounded-lg text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition text-center">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-[var(--color-red)] text-white rounded-lg hover:bg-[var(--color-red-dark)] transition font-medium text-center">
                    <i class="fa-solid fa-save mr-2"></i>Save Address
                </button>
            </div>
        </form>
    </div>
</div>
@endsection