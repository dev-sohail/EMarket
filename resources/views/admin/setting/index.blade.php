@extends('layouts.admin')
@section('title', 'Settings')
@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Site Settings</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Configure your store information, SEO, orders, and more.</p>
    </div>
</div>
@endsection
@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')

    {{-- Flash + validation alerts are rendered once by
         resources/views/components/admin/alerts.blade.php (included from
         layouts.admin). Do not repeat them here or every message shows twice. --}}

    <div class="flex gap-6">

        <aside class="w-64 flex-shrink-0">
            <nav class="sticky top-6 bg-[var(--color-paper)] rounded-lg border hairline p-3 space-y-1 max-h-[calc(100vh-8rem)] overflow-y-auto">
                <p class="px-3 text-xs font-semibold text-[var(--color-steel)] uppercase tracking-wider mb-2">Settings</p>
                <a href="#general" class="nav-link flex items-center px-3 py-2 rounded-lg hover:bg-[var(--color-paper-dim)] transition-colors"><i class="fa-solid fa-house mr-2 text-[var(--color-steel)]"></i> General</a>
                <a href="#regional" class="nav-link flex items-center px-3 py-2 rounded-lg hover:bg-[var(--color-paper-dim)] transition-colors"><i class="fa-solid fa-globe mr-2 text-[var(--color-steel)]"></i> Regional</a>
                <a href="#contact" class="nav-link flex items-center px-3 py-2 rounded-lg hover:bg-[var(--color-paper-dim)] transition-colors"><i class="fa-solid fa-location-dot mr-2 text-[var(--color-steel)]"></i> Contact &amp; Location</a>
                <a href="#seo" class="nav-link flex items-center px-3 py-2 rounded-lg hover:bg-[var(--color-paper-dim)] transition-colors"><i class="fa-solid fa-magnifying-glass mr-2 text-[var(--color-steel)]"></i> SEO</a>
                <a href="#orders" class="nav-link flex items-center px-3 py-2 rounded-lg hover:bg-[var(--color-paper-dim)] transition-colors"><i class="fa-solid fa-shopping-cart mr-2 text-[var(--color-steel)]"></i> Order &amp; Inventory</a>
                <a href="#shipping" class="nav-link flex items-center px-3 py-2 rounded-lg hover:bg-[var(--color-paper-dim)] transition-colors"><i class="fa-solid fa-truck mr-2 text-[var(--color-steel)]"></i> Shipping &amp; Tax</a>
                <a href="#social" class="nav-link flex items-center px-3 py-2 rounded-lg hover:bg-[var(--color-paper-dim)] transition-colors"><i class="fa-solid fa-share-nodes mr-2 text-[var(--color-steel)]"></i> Social Media</a>
                <a href="#display" class="nav-link flex items-center px-3 py-2 rounded-lg hover:bg-[var(--color-paper-dim)] transition-colors"><i class="fa-solid fa-display mr-2 text-[var(--color-steel)]"></i> Display</a>
                <a href="#theme" class="nav-link flex items-center px-3 py-2 rounded-lg hover:bg-[var(--color-paper-dim)] transition-colors"><i class="fa-solid fa-palette mr-2 text-[var(--color-steel)]"></i> Theme Colors</a>
                <a href="#account" class="nav-link flex items-center px-3 py-2 rounded-lg hover:bg-[var(--color-paper-dim)] transition-colors"><i class="fa-solid fa-user-gear mr-2 text-[var(--color-steel)]"></i> Account Settings</a>
            </nav>
        </aside>

        <div class="flex-1 min-w-0 space-y-6">

            {{-- General Settings --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6" id="general">
                <h2 class="font-semibold text-[var(--color-ink)] mb-1 flex items-center"><i class="fa-solid fa-house mr-2 text-[var(--color-red)]"></i> General Settings</h2>
                <p class="text-xs text-[var(--color-steel)] mb-4">Basic information about your store.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Store Name</label>
                        <input type="text" name="store_name" value="{{ old('store_name', $settings['store_name'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('store_name') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Store Tagline</label>
                        <input type="text" name="store_tagline" value="{{ old('store_tagline', $settings['store_tagline'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('store_tagline') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Store Description</label>
                        <textarea name="store_description" rows="3" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">{{ old('store_description', $settings['store_description'] ?? '') }}</textarea>
                        @error('store_description') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Top Bar Message</label>
                        <input type="text" name="store_top_bar_message" value="{{ old('store_top_bar_message', $settings['store_top_bar_message'] ?? '') }}" placeholder="Free shipping on orders over {{ currency_symbol() }} 99" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('store_top_bar_message') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-[var(--color-steel)]">Displayed in the store top bar. Leave empty to hide.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Store Logo</label>
                        @if($settings['store_logo'] ?? '')
                            <img src="{{ asset('storage/' . $settings['store_logo']) }}" class="w-20 h-20 object-cover rounded mb-2 border">
                        @endif
                        <input type="file" name="store_logo" accept="image/*" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('store_logo') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-[var(--color-steel)]">Max 2MB. PNG, JPG, or WebP.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Favicon</label>
                        @if($settings['favicon'] ?? '')
                            <img src="{{ asset('storage/' . $settings['favicon']) }}" class="w-12 h-12 object-cover rounded mb-2 border">
                        @endif
                        <input type="file" name="favicon" accept="image/*" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('favicon') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-[var(--color-steel)]">Max 500KB. ICO, PNG, JPG, or WebP.</p>
                    </div>
                </div>
            </div>

            {{-- Regional Settings --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6" id="regional">
                <h2 class="font-semibold text-[var(--color-ink)] mb-1 flex items-center"><i class="fa-solid fa-globe mr-2 text-[var(--color-red)]"></i> Regional Settings</h2>
                <p class="text-xs text-[var(--color-steel)] mb-4">Time zone used across the storefront and admin.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Timezone</label>
                        <select name="timezone" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                            @foreach(\DateTimeZone::listIdentifiers() as $tz)
                                <option value="{{ $tz }}" {{ old('timezone', $settings['timezone'] ?? '') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                            @endforeach
                        </select>
                        @error('timezone') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-[var(--color-steel)]">Used for banner scheduling, orders, and dates.</p>
                    </div>
                </div>
            </div>

            {{-- Contact & Location --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6" id="contact">
                <h2 class="font-semibold text-[var(--color-ink)] mb-1 flex items-center"><i class="fa-solid fa-location-dot mr-2 text-[var(--color-red)]"></i> Contact &amp; Location</h2>
                <p class="text-xs text-[var(--color-steel)] mb-4">How customers reach you.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Email</label>
                        <input type="email" name="store_email" value="{{ old('store_email', $settings['store_email'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('store_email') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Phone</label>
                        <input type="text" name="store_phone" value="{{ old('store_phone', $settings['store_phone'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('store_phone') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">WhatsApp</label>
                        <input type="text" name="whatsapp" value="{{ old('whatsapp', $settings['whatsapp'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('whatsapp') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Business Hours</label>
                        <input type="text" name="business_hours" value="{{ old('business_hours', $settings['business_hours'] ?? '') }}" placeholder="Mon - Sat: 9am - 6pm" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('business_hours') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Address</label>
                        <textarea name="store_address" rows="2" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">{{ old('store_address', $settings['store_address'] ?? '') }}</textarea>
                        @error('store_address') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">City</label>
                        <input type="text" name="city" value="{{ old('city', $settings['city'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('city') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Store Country</label>
                        <select name="default_country" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                            <option value="PK" {{ old('default_country', $settings['default_country'] ?? '') === 'PK' ? 'selected' : '' }}>PK</option>
                            <option value="US" {{ old('default_country', $settings['default_country'] ?? '') === 'US' ? 'selected' : '' }}>United States (USD)</option>
                            <option value="GB" {{ old('default_country', $settings['default_country'] ?? '') === 'GB' ? 'selected' : '' }}>United Kingdom (GBP)</option>
                            <option value="EU" {{ old('default_country', $settings['default_country'] ?? '') === 'EU' ? 'selected' : '' }}>European Union (EUR)</option>
                            <option value="IN" {{ old('default_country', $settings['default_country'] ?? '') === 'IN' ? 'selected' : '' }}>India (INR)</option>
                            <option value="CA" {{ old('default_country', $settings['default_country'] ?? '') === 'CA' ? 'selected' : '' }}>Canada</option>
                            <option value="AU" {{ old('default_country', $settings['default_country'] ?? '') === 'AU' ? 'selected' : '' }}>Australia</option>
                            <option value="AE" {{ old('default_country', $settings['default_country'] ?? '') === 'AE' ? 'selected' : '' }}>UAE</option>
                            {{-- Add more countries as needed --}}
                        </select>
                        @error('default_country') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- SEO Settings --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6" id="seo">
                <h2 class="font-semibold text-[var(--color-ink)] mb-1 flex items-center"><i class="fa-solid fa-magnifying-glass mr-2 text-[var(--color-red)]"></i> SEO Settings</h2>
                <p class="text-xs text-[var(--color-steel)] mb-4">Metadata shown in search engines and social shares.</p>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Meta Title</label>
                        <input type="text" name="meta_title" value="{{ old('meta_title', $settings['meta_title'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('meta_title') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Meta Description</label>
                        <textarea name="meta_description" rows="2" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">{{ old('meta_description', $settings['meta_description'] ?? '') }}</textarea>
                        @error('meta_description') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Meta Keywords</label>
                        <input type="text" name="meta_keywords" value="{{ old('meta_keywords', $settings['meta_keywords'] ?? '') }}" placeholder="ecommerce, shop" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('meta_keywords') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Order & Inventory --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6" id="orders">
                <h2 class="font-semibold text-[var(--color-ink)] mb-1 flex items-center"><i class="fa-solid fa-shopping-cart mr-2 text-[var(--color-red)]"></i> Order &amp; Inventory</h2>
                <p class="text-xs text-[var(--color-steel)] mb-4">Order numbering and stock alerts.</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Order Prefix</label>
                        <input type="text" name="order_prefix" value="{{ old('order_prefix', $settings['order_prefix'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('order_prefix') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Invoice Prefix</label>
                        <input type="text" name="invoice_prefix" value="{{ old('invoice_prefix', $settings['invoice_prefix'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('invoice_prefix') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Low Stock Threshold</label>
                        <input type="number" name="low_stock_threshold" value="{{ old('low_stock_threshold', $settings['low_stock_threshold'] ?? '') }}" min="0" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('low_stock_threshold') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Shipping & Tax --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6" id="shipping">
                <h2 class="font-semibold text-[var(--color-ink)] mb-1 flex items-center"><i class="fa-solid fa-truck mr-2 text-[var(--color-red)]"></i> Shipping &amp; Tax</h2>
                <p class="text-xs text-[var(--color-steel)] mb-4">Default rates used when no zone/rule matches.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Default Shipping Cost</label>
                        <input type="number" name="default_shipping_cost" value="{{ old('default_shipping_cost', $settings['default_shipping_cost'] ?? '') }}" step="0.01" min="0" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('default_shipping_cost') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Default Tax Rate (%)</label>
                        <input type="number" name="default_tax_rate" value="{{ old('default_tax_rate', $settings['default_tax_rate'] ?? '') }}" step="0.01" min="0" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('default_tax_rate') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Social Media --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6" id="social">
                <h2 class="font-semibold text-[var(--color-ink)] mb-1 flex items-center"><i class="fa-brands fa-share-nodes mr-2 text-[var(--color-red)]"></i> Social Media</h2>
                <p class="text-xs text-[var(--color-steel)] mb-4">Links to your social profiles.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1"><i class="fa-brands fa-facebook text-[var(--color-red)] mr-1"></i>Facebook URL</label>
                        <input type="url" name="facebook_url" value="{{ old('facebook_url', $settings['facebook_url'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('facebook_url') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1"><i class="fa-brands fa-instagram text-[var(--color-red)] mr-1"></i>Instagram URL</label>
                        <input type="url" name="instagram_url" value="{{ old('instagram_url', $settings['instagram_url'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('instagram_url') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1"><i class="fa-brands fa-x-twitter text-[var(--color-ink)] mr-1"></i>Twitter URL</label>
                        <input type="url" name="twitter_url" value="{{ old('twitter_url', $settings['twitter_url'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('twitter_url') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1"><i class="fa-brands fa-youtube text-[var(--color-red)] mr-1"></i>YouTube URL</label>
                        <input type="url" name="youtube_url" value="{{ old('youtube_url', $settings['youtube_url'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('youtube_url') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1"><i class="fa-brands fa-tiktok text-[var(--color-ink)] mr-1"></i>TikTok URL</label>
                        <input type="url" name="tiktok_url" value="{{ old('tiktok_url', $settings['tiktok_url'] ?? '') }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('tiktok_url') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Display Settings --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6" id="display">
                <h2 class="font-semibold text-[var(--color-ink)] mb-1 flex items-center"><i class="fa-solid fa-display mr-2 text-[var(--color-red)]"></i> Display Settings</h2>
                <p class="text-xs text-[var(--color-steel)] mb-4">Catalog and currency preferences.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Currency</label>
                        <select name="currency" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                            <option value="CHF" {{ old('currency', $settings['currency'] ?? '') === 'CHF' ? 'selected' : '' }}>CHF (Swiss Franc)</option>
                            <option value="USD" {{ old('currency', $settings['currency'] ?? '') === 'USD' ? 'selected' : '' }}>USD (US Dollar)</option>
                            <option value="EUR" {{ old('currency', $settings['currency'] ?? '') === 'EUR' ? 'selected' : '' }}>EUR (Euro)</option>
                            <option value="GBP" {{ old('currency', $settings['currency'] ?? '') === 'GBP' ? 'selected' : '' }}>GBP (British Pound)</option>
                            <option value="INR" {{ old('currency', $settings['currency'] ?? '') === 'INR' ? 'selected' : '' }}>INR (Indian Rupee)</option>
                            <option value="PKR" {{ old('currency', $settings['currency'] ?? '') === 'PKR' ? 'selected' : '' }}>PKR (Pakistani Rupee)</option>
                            <option value="CAD" {{ old('currency', $settings['currency'] ?? '') === 'CAD' ? 'selected' : '' }}>CAD (Canadian Dollar)</option>
                            <option value="AUD" {{ old('currency', $settings['currency'] ?? '') === 'AUD' ? 'selected' : '' }}>AUD (Australian Dollar)</option>
                        </select>
                        @error('currency') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Products Per Page</label>
                        <input type="number" name="products_per_page" value="{{ old('products_per_page', $settings['products_per_page'] ?? '') }}" min="1" max="100" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('products_per_page') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Theme Colors --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6" id="theme">
                <h2 class="font-semibold text-[var(--color-ink)] mb-1 flex items-center"><i class="fa-solid fa-palette mr-2 text-[var(--color-red)]"></i> Theme Colors</h2>
                <p class="text-xs text-[var(--color-steel)] mb-4">Customize the major colors used across the storefront.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Ink <span class="font-normal text-xs text-[var(--color-steel)]">Primary text</span> <span class="text-xs text-[var(--color-steel)]">--color-ink</span></label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="color" name="color_ink" value="{{ old('color_ink', $settings['color_ink'] ?? '#14171a') }}" class="absolute inset-0 w-full h-10 rounded-lg border hairline cursor-pointer opacity-0">
                                    <div class="absolute inset-0 flex items-center px-2 pointer-events-none">
                                        <span class="color-hex text-xs font-mono text-[var(--color-ink)] bg-[var(--color-paper)]/50 px-1 rounded leading-none">{{ old('color_ink', $settings['color_ink'] ?? '#14171a') }}</span>
                                    </div>
                                </div>
                                <div class="w-10 h-10 rounded-lg border hairline overflow-hidden flex-shrink-0">
                                    <div class="w-full h-full" style="background-color: {{ old('color_ink', $settings['color_ink'] ?? '#14171a') }}"></div>
                                </div>
                            </div>
                            @error('color_ink') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Ink Soft <span class="font-normal text-xs text-[var(--color-steel)]">Secondary text</span> <span class="text-xs text-[var(--color-steel)]">--color-ink-soft</span></label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="color" name="color_ink_soft" value="{{ old('color_ink_soft', $settings['color_ink_soft'] ?? '#40454b') }}" class="absolute inset-0 w-full h-10 rounded-lg border hairline cursor-pointer opacity-0">
                                    <div class="absolute inset-0 flex items-center px-2 pointer-events-none">
                                        <span class="color-hex text-xs font-mono text-[var(--color-ink)] bg-[var(--color-paper)]/50 px-1 rounded leading-none">{{ old('color_ink_soft', $settings['color_ink_soft'] ?? '#40454b') }}</span>
                                    </div>
                                </div>
                                <div class="w-10 h-10 rounded-lg border hairline overflow-hidden flex-shrink-0">
                                    <div class="w-full h-full" style="background-color: {{ old('color_ink_soft', $settings['color_ink_soft'] ?? '#40454b') }}"></div>
                                </div>
                            </div>
                            @error('color_ink_soft') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Paper <span class="font-normal text-xs text-[var(--color-steel)]">Page background</span> <span class="text-xs text-[var(--color-steel)]">--color-paper</span></label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="color" name="color_paper" value="{{ old('color_paper', $settings['color_paper'] ?? '#faf9f6') }}" class="absolute inset-0 w-full h-10 rounded-lg border hairline cursor-pointer opacity-0">
                                    <div class="absolute inset-0 flex items-center px-2 pointer-events-none">
                                        <span class="color-hex text-xs font-mono text-[var(--color-ink)] bg-[var(--color-paper)]/50 px-1 rounded leading-none">{{ old('color_paper', $settings['color_paper'] ?? '#faf9f6') }}</span>
                                    </div>
                                </div>
                                <div class="w-10 h-10 rounded-lg border hairline overflow-hidden flex-shrink-0">
                                    <div class="w-full h-full" style="background-color: {{ old('color_paper', $settings['color_paper'] ?? '#faf9f6') }}"></div>
                                </div>
                            </div>
                            @error('color_paper') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Paper Dim <span class="font-normal text-xs text-[var(--color-steel)]">Subtle surfaces</span> <span class="text-xs text-[var(--color-steel)]">--color-paper-dim</span></label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="color" name="color_paper_dim" value="{{ old('color_paper_dim', $settings['color_paper_dim'] ?? '#f0efe9') }}" class="absolute inset-0 w-full h-10 rounded-lg border hairline cursor-pointer opacity-0">
                                    <div class="absolute inset-0 flex items-center px-2 pointer-events-none">
                                        <span class="color-hex text-xs font-mono text-[var(--color-ink)] bg-[var(--color-paper)]/50 px-1 rounded leading-none">{{ old('color_paper_dim', $settings['color_paper_dim'] ?? '#f0efe9') }}</span>
                                    </div>
                                </div>
                                <div class="w-10 h-10 rounded-lg border hairline overflow-hidden flex-shrink-0">
                                    <div class="w-full h-full" style="background-color: {{ old('color_paper_dim', $settings['color_paper_dim'] ?? '#f0efe9') }}"></div>
                                </div>
                            </div>
                            @error('color_paper_dim') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Line <span class="font-normal text-xs text-[var(--color-steel)]">Borders &amp; dividers</span> <span class="text-xs text-[var(--color-steel)]">--color-line</span></label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="color" name="color_line" value="{{ old('color_line', $settings['color_line'] ?? '#dedcd4') }}" class="absolute inset-0 w-full h-10 rounded-lg border hairline cursor-pointer opacity-0">
                                    <div class="absolute inset-0 flex items-center px-2 pointer-events-none">
                                        <span class="color-hex text-xs font-mono text-[var(--color-ink)] bg-[var(--color-paper)]/50 px-1 rounded leading-none">{{ old('color_line', $settings['color_line'] ?? '#dedcd4') }}</span>
                                    </div>
                                </div>
                                <div class="w-10 h-10 rounded-lg border hairline overflow-hidden flex-shrink-0">
                                    <div class="w-full h-full" style="background-color: {{ old('color_line', $settings['color_line'] ?? '#dedcd4') }}"></div>
                                </div>
                            </div>
                            @error('color_line') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Steel <span class="font-normal text-xs text-[var(--color-steel)]">Muted text</span> <span class="text-xs text-[var(--color-steel)]">--color-steel</span></label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="color" name="color_steel" value="{{ old('color_steel', $settings['color_steel'] ?? '#6b6f76') }}" class="absolute inset-0 w-full h-10 rounded-lg border hairline cursor-pointer opacity-0">
                                    <div class="absolute inset-0 flex items-center px-2 pointer-events-none">
                                        <span class="color-hex text-xs font-mono text-[var(--color-ink)] bg-[var(--color-paper)]/50 px-1 rounded leading-none">{{ old('color_steel', $settings['color_steel'] ?? '#6b6f76') }}</span>
                                    </div>
                                </div>
                                <div class="w-10 h-10 rounded-lg border hairline overflow-hidden flex-shrink-0">
                                    <div class="w-full h-full" style="background-color: {{ old('color_steel', $settings['color_steel'] ?? '#6b6f76') }}"></div>
                                </div>
                            </div>
                            @error('color_steel') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Red <span class="font-normal text-xs text-[var(--color-steel)]">Brand accent</span> <span class="text-xs text-[var(--color-steel)]">--color-red</span></label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="color" name="color_red" value="{{ old('color_red', $settings['color_red'] ?? '#d6001c') }}" class="absolute inset-0 w-full h-10 rounded-lg border hairline cursor-pointer opacity-0">
                                    <div class="absolute inset-0 flex items-center px-2 pointer-events-none">
                                        <span class="color-hex text-xs font-mono text-[var(--color-ink)] bg-[var(--color-paper)]/50 px-1 rounded leading-none">{{ old('color_red', $settings['color_red'] ?? '#d6001c') }}</span>
                                    </div>
                                </div>
                                <div class="w-10 h-10 rounded-lg border hairline overflow-hidden flex-shrink-0">
                                    <div class="w-full h-full" style="background-color: {{ old('color_red', $settings['color_red'] ?? '#d6001c') }}"></div>
                                </div>
                            </div>
                            @error('color_red') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Red Dark <span class="font-normal text-xs text-[var(--color-steel)]">Accent hover</span> <span class="text-xs text-[var(--color-steel)]">--color-red-dark</span></label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="color" name="color_red_dark" value="{{ old('color_red_dark', $settings['color_red_dark'] ?? '#a80016') }}" class="absolute inset-0 w-full h-10 rounded-lg border hairline cursor-pointer opacity-0">
                                    <div class="absolute inset-0 flex items-center px-2 pointer-events-none">
                                        <span class="color-hex text-xs font-mono text-[var(--color-ink)] bg-[var(--color-paper)]/50 px-1 rounded leading-none">{{ old('color_red_dark', $settings['color_red_dark'] ?? '#a80016') }}</span>
                                    </div>
                                </div>
                                <div class="w-10 h-10 rounded-lg border hairline overflow-hidden flex-shrink-0">
                                    <div class="w-full h-full" style="background-color: {{ old('color_red_dark', $settings['color_red_dark'] ?? '#a80016') }}"></div>
                                </div>
                            </div>
                            @error('color_red_dark') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Sale <span class="font-normal text-xs text-[var(--color-steel)]">Sale &amp; success</span> <span class="text-xs text-[var(--color-steel)]">--color-sale</span></label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="color" name="color_sale" value="{{ old('color_sale', $settings['color_sale'] ?? '#1b7a43') }}" class="absolute inset-0 w-full h-10 rounded-lg border hairline cursor-pointer opacity-0">
                                    <div class="absolute inset-0 flex items-center px-2 pointer-events-none">
                                        <span class="color-hex text-xs font-mono text-[var(--color-ink)] bg-[var(--color-paper)]/50 px-1 rounded leading-none">{{ old('color_sale', $settings['color_sale'] ?? '#1b7a43') }}</span>
                                    </div>
                                </div>
                                <div class="w-10 h-10 rounded-lg border hairline overflow-hidden flex-shrink-0">
                                    <div class="w-full h-full" style="background-color: {{ old('color_sale', $settings['color_sale'] ?? '#1b7a43') }}"></div>
                                </div>
                            </div>
                            @error('color_sale') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                </div>
            </div>

            {{-- Account Settings (Toggles) --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6" id="account">
                <h2 class="font-semibold text-[var(--color-ink)] mb-1 flex items-center"><i class="fa-solid fa-user-gear mr-2 text-[var(--color-red)]"></i> Account Settings</h2>
                <p class="text-xs text-[var(--color-steel)] mb-4">Customer registration and checkout options.</p>
                <div class="space-y-3">
                    <label class="flex items-center justify-between p-3 border rounded-lg hover:bg-[var(--color-paper-dim)] cursor-pointer">
                        <div>
                            <span class="text-sm font-medium text-[var(--color-ink-soft)]">Guest Checkout</span>
                            <p class="text-xs text-[var(--color-steel)]">Allow customers to order without an account.</p>
                        </div>
                        <input type="checkbox" name="guest_checkout" value="1" {{ filter_var($settings['guest_checkout'] ?? '', FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-indigo-500">
                    </label>
                    <label class="flex items-center justify-between p-3 border rounded-lg hover:bg-[var(--color-paper-dim)] cursor-pointer">
                        <div>
                            <span class="text-sm font-medium text-[var(--color-ink-soft)]">Customer Registration</span>
                            <p class="text-xs text-[var(--color-steel)]">Allow new customers to create an account.</p>
                        </div>
                        <input type="checkbox" name="registration" value="1" {{ filter_var($settings['registration'] ?? '', FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-indigo-500">
                    </label>
                    <label class="flex items-center justify-between p-4 border rounded-lg hover:bg-[var(--color-paper-dim)] cursor-pointer transition-all">
                        <div>
                            <span class="text-sm font-medium text-[var(--color-ink-soft)]">Email Verification</span>
                            <p class="text-xs text-[var(--color-steel)]">Require customers to verify their email.</p>
                        </div>
                        <input type="checkbox" name="email_verification" value="1" {{ filter_var($settings['email_verification'] ?? '', FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }} class="w-5 h-5 rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-2 focus:ring-[var(--color-red)] focus:ring-offset-0">
                    </label>
                    <label class="flex items-center justify-between p-4 border rounded-lg hover:bg-[var(--color-paper-dim)] cursor-pointer transition-all">
                        <div>
                            <span class="text-sm font-medium text-[var(--color-ink-soft)]">Show Reviews</span>
                            <p class="text-xs text-[var(--color-steel)]">Display customer reviews on product pages and product cards.</p>
                        </div>
                        <input type="checkbox" name="reviews_enabled" value="1" {{ filter_var($settings['reviews_enabled'] ?? '', FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }} class="w-5 h-5 rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-2 focus:ring-[var(--color-red)] focus:ring-offset-0">
                    </label>
                    <label class="flex items-center justify-between p-4 border rounded-lg hover:bg-[var(--color-paper-dim)] cursor-pointer transition-all">
                        <div>
                            <span class="text-sm font-medium text-[var(--color-ink-soft)]">Allow Returns</span>
                            <p class="text-xs text-[var(--color-steel)]">Let customers submit and track product returns from their account.</p>
                        </div>
                        <input type="checkbox" name="returns_enabled" value="1" {{ filter_var($settings['returns_enabled'] ?? '', FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }} class="w-5 h-5 rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-2 focus:ring-[var(--color-red)] focus:ring-offset-0">
                    </label>
                    <label class="flex items-center justify-between p-4 border rounded-lg hover:bg-[var(--color-paper-dim)] cursor-pointer transition-all">
                        <div>
                            <span class="text-sm font-medium text-[var(--color-ink-soft)]">Show Blog on Storefront</span>
                            <p class="text-xs text-[var(--color-steel)]">Display the blog section at <code class="text-[10px] bg-[var(--color-paper-dim)] px-1 rounded">/blog</code>. Individual posts still need to be Published and marked "Show on site".</p>
                        </div>
                        <input type="checkbox" name="blogs_enabled" value="1" {{ filter_var($settings['blogs_enabled'] ?? '', FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }} class="w-5 h-5 rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-2 focus:ring-[var(--color-red)] focus:ring-offset-0">
                    </label>
                    <label id="affiliate" class="scroll-mt-6 flex items-center justify-between p-4 border rounded-lg hover:bg-[var(--color-paper-dim)] cursor-pointer transition-all">
                        <div>
                            <span class="text-sm font-medium text-[var(--color-ink-soft)]">Enable Affiliate Program</span>
                            <p class="text-xs text-[var(--color-steel)]">Allow affiliation across the store. When enabled, affiliate/referral tracking can be used.</p>
                        </div>
                        <input type="checkbox" name="affiliate_enabled" value="1" {{ filter_var($settings['affiliate_enabled'] ?? '', FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }} class="w-5 h-5 rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-2 focus:ring-[var(--color-red)] focus:ring-offset-0">
                    </label>
                </div>
            </div>

            <div class="sticky bottom-0 bg-[var(--color-paper)] border-t hairline p-4 flex justify-end">
                <button type="submit" class="bg-[var(--color-red)] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[var(--color-red-dark)] flex items-center">
                    <i class="fa-solid fa-save mr-2"></i> Save Settings
                </button>
            </div>
        </div>
    </div>
</form>
@endsection
