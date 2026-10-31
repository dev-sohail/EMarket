@extends('layouts.admin')
@section('title', 'Chat Shop')
@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Chat Shop</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Configure how customers reach you from the storefront.</p>
    </div>
</div>
@endsection
@section('content')
<div x-data="{ tab: 'whatsapp' }" class="space-y-6">
    <div class="bg-[var(--color-paper)] rounded-lg border p-2 inline-flex flex-wrap gap-1">
        <button type="button" @click="tab = 'whatsapp'" :class="tab === 'whatsapp' ? 'bg-[var(--color-red)] text-white' : 'text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)]'" class="px-4 py-2 rounded-md text-sm font-medium transition">
            <i class="fa-brands fa-whatsapp mr-2"></i>WhatsApp Link/Icon
        </button>
        <button type="button" @click="tab = 't2'" :class="tab === 't2' ? 'bg-[var(--color-red)] text-white' : 'text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)]'" class="px-4 py-2 rounded-md text-sm font-medium transition">
            T2
            <span class="ml-2 text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-[var(--color-paper-dim)] text-[var(--color-steel)]">Soon</span>
        </button>
        <button type="button" @click="tab = 't3'" :class="tab === 't3' ? 'bg-[var(--color-red)] text-white' : 'text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)]'" class="px-4 py-2 rounded-md text-sm font-medium transition">
            T3
            <span class="ml-2 text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-[var(--color-paper-dim)] text-[var(--color-steel)]">Soon</span>
        </button>
    </div>

    <form method="POST" action="{{ route('admin.chat-shop.update') }}">
        @csrf @method('PUT')

        <div x-show="tab === 'whatsapp'" x-cloak class="bg-[var(--color-paper)] rounded-lg border p-6">
            <div class="flex items-center justify-between mb-1">
                <h2 class="font-semibold text-[var(--color-ink)]">WhatsApp Link/Icon</h2>
                @php $waLink = !empty($chatSettings['whatsapp_phone']) ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $chatSettings['whatsapp_phone']) : ''; @endphp
                @if($waLink)
                    <a href="{{ $waLink }}" target="_blank" class="text-xs text-[var(--color-steel)] hover:text-[var(--color-red)] flex items-center gap-1">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Preview chat link
                    </a>
                @endif
            </div>
            <p class="text-xs text-[var(--color-steel)] mb-4">Shows a floating WhatsApp icon on the storefront that opens a direct chat.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">WhatsApp Phone Number</label>
                    <input type="text" name="whatsapp_phone" value="{{ old('whatsapp_phone', $chatSettings['whatsapp_phone'] ?? '') }}" placeholder="923001234567" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                    @error('whatsapp_phone') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-[var(--color-steel)]">Include country code, digits only (e.g. 923001234567).</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Default Message (optional)</label>
                    <input type="text" name="whatsapp_message" value="{{ old('whatsapp_message', $chatSettings['whatsapp_message'] ?? '') }}" placeholder="Hi! I'm interested in..." class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                    @error('whatsapp_message') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="whatsapp_show_on_site" value="0">
                        <input type="checkbox" name="whatsapp_show_on_site" value="1" {{ old('whatsapp_show_on_site', $chatSettings['whatsapp_show_on_site'] ?? false) ? 'checked' : '' }} class="w-5 h-5 rounded border-gray-300 text-[var(--color-red)] focus:ring-[var(--color-red)]">
                        <span>
                            <span class="block text-sm font-medium text-[var(--color-ink)]">Show WhatsApp icon on storefront</span>
                            <span class="block text-xs text-[var(--color-steel)]">When enabled, a floating WhatsApp button appears on every storefront page.</span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <div x-show="tab === 't2'" x-cloak class="bg-[var(--color-paper)] rounded-lg border p-6">
            <div class="flex items-center gap-3 mb-2">
                <h2 class="font-semibold text-[var(--color-ink)]">T2</h2>
                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-[var(--color-paper-dim)] text-[var(--color-steel)]">Coming soon</span>
            </div>
            <p class="text-sm text-[var(--color-steel)]">Reserved for future use. This tab will host an AI-powered chat assistant that will appear as a cornered helper on the storefront.</p>
        </div>

        <div x-show="tab === 't3'" x-cloak class="bg-[var(--color-paper)] rounded-lg border p-6">
            <div class="flex items-center gap-3 mb-2">
                <h2 class="font-semibold text-[var(--color-ink)]">T3</h2>
                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-[var(--color-paper-dim)] text-[var(--color-steel)]">Coming soon</span>
            </div>
            <p class="text-sm text-[var(--color-steel)]">Reserved for future use. This tab will host a manual live-chat helper for storefront visitors.</p>
        </div>

        <div x-show="tab === 'whatsapp'" x-cloak class="flex justify-end">
            <button type="submit" class="px-5 py-2.5 bg-[var(--color-red)] text-white rounded-lg hover:bg-[var(--color-red-dark)] transition font-medium">
                <i class="fa-solid fa-floppy-disk mr-2"></i>Save Changes
            </button>
        </div>
    </form>
</div>

<style>[x-cloak]{display:none!important}</style>
@endsection
