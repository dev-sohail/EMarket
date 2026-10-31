{{-- Admin Sidebar --}}
<aside id="admin-sidebar" class="w-64 bg-[var(--color-ink)] text-gray-300 flex-shrink-0 transform lg:transform-none transition-transform duration-200 -translate-x-full lg:translate-x-0 fixed lg:static h-full z-30 overflow-y-auto">
    {{-- Logo --}}
    <div class="p-4 border-b border-gray-800">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
            <div class="w-8 h-8 bg-[var(--color-red)] rounded flex items-center justify-center text-white font-bold text-sm font-display">{{ substr(config('app.name', ''), 0, 1) }}</div>
            <span class="text-white font-display font-semibold text-lg tracking-tight">{{ config('app.name', '') }}</span>
        </a>
    </div>

    {{-- Navigation --}}
    <nav class="p-3 space-y-1">
        {{-- Dashboard --}}
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.dashboard') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dashboard
        </a>

        {{-- Catalog --}}
        <div class="pt-3">
            <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Catalog</p>
            <a href="{{ route('admin.products.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.products.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                Products
            </a>
            <a href="{{ route('admin.categories.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.categories.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Categories
            </a>
            <a href="{{ route('admin.brands.index', []) }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.brands.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                Brands
            </a>
            <a href="{{ route('admin.tags.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.tags.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14m-4 4l4-4-4-4"/></svg>
                Tags
            </a>
            <a href="{{ route('admin.attributes.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.attributes.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.983 0a9.97 9.97 0 013.467 1.657A9.995 9.995 0 0116.7 20.343 9.97 9.97 0 0112.217 22.92v-2.642a7.5 7.5 0 10-2.7-12.4z"/></svg>
                Attributes
            </a>
            <a href="{{ route('admin.import-export.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.import-export.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Import / Export
            </a>
        </div>

        {{-- Sales --}}
        <div class="pt-3">
            <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Sales</p>
<a href="{{ route('admin.orders.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.orders.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                Orders
                @if($pendingOrdersCount > 0)
                    <span class="ml-auto bg-[var(--color-red)] text-white text-xs px-2 py-0.5 rounded-full">{{ $pendingOrdersCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.order-processor.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.order-processor.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Order Processor
            </a>
            <a href="{{ route('admin.coupons.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.coupons.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                Coupons
            </a>
            <a href="{{ route('admin.returns.index', []) }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.returns.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                Returns
            </a>
            <a href="{{ route('admin.campaigns.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.campaigns.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/></svg>
                Campaigns
            </a>
            <a href="{{ route('admin.flash-sales.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.flash-sales.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Flash Sales
            </a>
            <a href="{{ route('admin.shipping.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.shipping.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Shipping
            </a>
            <a href="{{ route('admin.payment-methods.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.payment-methods.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Payment Methods
            </a>
            <a href="{{ route('admin.inventory.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.inventory.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Inventory
            </a>
        </div>

        {{-- Customers --}}
        <div class="pt-3">
            <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Customers</p>
            <a href="{{ route('admin.customers.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.customers.index') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                Customers
            </a>
            <a href="{{ route('admin.delete-approval') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.delete-approval') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete Approval
            </a>
            <a href="{{ route('admin.enquiries.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.enquiries.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                Enquiries
                @php $enquiryCount = $enquiryCount ?? 0; @endphp
                @if($enquiryCount > 0)
                    <span class="ml-auto bg-[var(--color-red)] text-white text-xs px-2 py-0.5 rounded-full">{{ $enquiryCount }}</span>
                @endif
            </a>
        </div>

        {{-- Reports --}}
        <div class="pt-3">
            <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Reports</p>
            <a href="{{ route('admin.reports.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.reports.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Reports
            </a>
            <a href="{{ route('admin.analytics.overview') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.analytics.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5V2a2 2 0 012-2h2a2 2 0 012 2v3m-4 0v6a2 2 0 002 2h2a2 2 0 002-2V5m-4 0H9z"/></svg>
                Analytics
            </a>
        </div>

        {{-- CMS --}}
        <div class="pt-3">
            <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">CMS</p>
            <a href="{{ route('admin.pages.index', []) }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.pages.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Pages
            </a>
            <a href="{{ route('admin.faqs.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.faqs.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                FAQs
            </a>
            <a href="{{ route('admin.reviews.index', []) }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.reviews.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11v-5m-1.414-9.414a2 2 0 012.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Reviews
            </a>
            <a href="{{ route('admin.banners.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.banners.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Banners
            </a>
            <a href="{{ route('admin.blogs.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.blogs.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                Blog Posts
            </a>
            <a href="{{ route('admin.popups.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.popups.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a4 4 0 004 4h10a4 4 0 004-4V7a4 4 0 00-4-4H7a4 4 0 014-4V7M3 7l9 5 9-5"/></svg>
                Popups
            </a>
        </div>

        {{-- Settings --}}
        <div class="pt-3">
            <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Settings</p>
            <a href="{{ route('admin.settings.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ (request()->routeIs('admin.settings.*') && request()->query('section') !== 'affiliate') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Settings
            </a>
            <a href="{{ route('admin.affiliate.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ (request()->routeIs('admin.affiliate.*') || (request()->routeIs('admin.settings.*') && request()->query('section') === 'affiliate')) ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Affiliates
            </a>
            <a href="{{ route('admin.chat-shop.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.chat-shop.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <i class="fa-brands fa-whatsapp text-lg w-5 h-5 flex items-center justify-center"></i>
                Chat Shop
            </a>
            <a href="{{ route('admin.tax.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.tax.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zM8 12H4a2 2 0 00-2 2v6a2 2 0 002 2h3.057m0 0a9.001 9.001 0 00-.057-4.057L6 16v-2a2 2 0 00-2-2V9a2 2 0 012-2h3m8 13a9 9 0 001-4H9a9 9 0 010-18 9.001 9.001 0 019 9v1"/></svg>
                Tax
            </a>
            <a href="{{ route('admin.invoices.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.invoices.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Invoices
            </a>
            <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.users.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Admin Users
            </a>
            <a href="{{ route('admin.roles.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.roles.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Roles & Permission
            </a>
            <a href="{{ route('admin.audit-logs.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.audit-logs.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Audit Logs
            </a>
            <a href="{{ route('admin.notifications.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.notifications.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14V11a3 3 0 00-6 0v3m0 0a3 3 0 11-6 0V9a6 6 0 0112 0v1m-3 5l2-2m-2 2v-5a1 1 0 10-2 0v5z"/></svg>
                Notifications
            </a>
            <a href="{{ route('admin.api-management.definitions.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded {{ request()->routeIs('admin.api-management.*') ? 'bg-[var(--color-red)] text-white' : 'hover:bg-gray-800 text-gray-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/></svg>
                API Management
            </a>
        </div>
    </nav>
</aside>