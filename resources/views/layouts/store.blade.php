<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="cart-count-url" content="{{ url('ajax/cart/count') }}">
    <meta name="cart-add-url" content="{{ url('ajax/cart/add') }}">
    <meta name="wishlist-toggle-url" content="{{ url('ajax/wishlist/toggle') }}">
    <meta name="login-url" content="{{ route('login') }}">
    <title>@yield('title', config('app.name', ''))</title>
    <meta name="description" content="@yield('meta_description', 'Your premium e-commerce destination')">
    @if(setting('favicon'))
        @php $faviconExt = pathinfo(setting('favicon'), PATHINFO_EXTENSION); @endphp
        <link rel="icon" type="{{ in_array($faviconExt, ['png','jpg','jpeg']) ? 'image/' . ($faviconExt === 'jpg' ? 'jpeg' : $faviconExt) : 'image/x-icon' }}" href="{{ asset('storage/' . setting('favicon')) }}">
    @endif
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3.17.4/dist/cdn.min.js"></script>
    @stack('styles')
    <style>[x-cloak]{display:none!important}</style>
    @if(setting('color_ink') || setting('color_red') || setting('color_sale'))
        <style>
            @if(setting('color_ink')) :root { --color-ink: {{ setting('color_ink') }}; } @endif
            @if(setting('color_ink_soft')) :root { --color-ink-soft: {{ setting('color_ink_soft') }}; } @endif
            @if(setting('color_paper')) :root { --color-paper: {{ setting('color_paper') }}; } @endif
            @if(setting('color_paper_dim')) :root { --color-paper-dim: {{ setting('color_paper_dim') }}; } @endif
            @if(setting('color_line')) :root { --color-line: {{ setting('color_line') }}; } @endif
            @if(setting('color_steel')) :root { --color-steel: {{ setting('color_steel') }}; } @endif
            @if(setting('color_red')) :root { --color-red: {{ setting('color_red') }}; } @endif
            @if(setting('color_red_dark')) :root { --color-red-dark: {{ setting('color_red_dark') }}; } @endif
            @if(setting('color_sale')) :root { --color-sale: {{ setting('color_sale') }}; } @endif
        </style>
    @endif
</head>
<body class="font-sans antialiased bg-[var(--color-paper)] text-[var(--color-ink)]">
    <div class="min-h-screen flex flex-col">
        {{-- Impersonation Bar --}}
        @if(session()->has('impersonator_id'))
            <div class="bg-[var(--color-ink)] text-white px-4 py-2 text-center text-sm flex items-center justify-center gap-4">
                <span class="font-medium">You are impersonating a customer</span>
                <form method="POST" action="{{ route('admin.customers.stop-impersonating') }}">
                    @csrf
                    <button type="submit" class="underline hover:no-underline font-semibold text-[var(--color-red)]">Stop Impersonating</button>
                </form>
            </div>
        @endif

        {{-- Header --}}
        @include('components.store.header')

        {{-- Hero Banner Placeholder --}}
        @if(!empty(trim($__env->yieldContent('hero'))))
            @yield('hero')
        @endif

        {{-- Main Content --}}
        <main class="flex-1">
            @include('components.store.flash')
            @yield('content')
        </main>

         {{-- Footer --}}
         @include('components.store.footer')
     </div>

     {{-- Popups --}}
     @include('components.store.popup')

     {{-- Mini Cart Sidebar --}}
    <div id="cart-sidebar" class="fixed inset-0 z-50 hidden" role="dialog" aria-label="Shopping Cart" aria-modal="true">
        <div class="absolute inset-0 bg-[var(--color-ink)]/50" data-toggle="cart-sidebar" aria-hidden="true"></div>
        <div class="absolute right-0 top-0 h-full w-full max-w-md bg-[var(--color-paper)] shadow-xl transform transition-transform duration-300 flex flex-col">
            <div class="flex items-center justify-between p-4 border-b hairline">
                <h3 class="font-display font-bold text-lg">Shopping Cart (<span data-cart-count>0</span>)</h3>
                <button type="button" data-toggle="cart-sidebar" class="p-2 hover:bg-[var(--color-paper-dim)] rounded-full" aria-label="Close cart">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div id="cart-items-sidebar" class="flex-1 overflow-y-auto p-4 space-y-4" style="max-height: calc(100vh - 200px);">
                <p class="text-[var(--color-steel)] text-center py-8">Your cart is empty</p>
            </div>
            <div class="border-t hairline p-4 space-y-3">
                <div class="flex justify-between text-lg font-semibold">
                    <span>Subtotal:</span>
                    <span id="cart-subtotal" class="price">{{ format_currency(0) }}</span>
                </div>
                <a href="{{ route('checkout') }}" class="btn btn-primary block w-full">Proceed to Checkout</a>
                <a href="{{ route('cart') }}" class="btn btn-outline block w-full text-[var(--color-ink)]">View Cart</a>
            </div>
        </div>
    </div>

    {{-- Mobile Menu --}}
    <div id="mobile-menu" class="fixed inset-0 z-50 hidden" role="dialog" aria-label="Mobile Menu" aria-modal="true">
        <div class="absolute inset-0 bg-[var(--color-ink)]/50" data-toggle="mobile-menu" aria-hidden="true"></div>
        <div class="absolute left-0 top-0 h-full w-80 bg-[var(--color-paper)] shadow-xl overflow-y-auto">
            <div class="flex items-center justify-between p-4 border-b hairline">
                <h3 class="font-display font-bold text-lg">Menu</h3>
                <button type="button" data-toggle="mobile-menu" class="p-2 hover:bg-[var(--color-paper-dim)] rounded-full" aria-label="Close menu">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <nav class="p-4">
                <ul class="space-y-1">
                    <li><a href="{{ route('home') }}" class="block px-4 py-2 rounded hover:bg-[var(--color-paper-dim)]">Home</a></li>
                    <li><a href="{{ route('shop') }}" class="block px-4 py-2 rounded hover:bg-[var(--color-paper-dim)]">Shop</a></li>
                    @php
                        $mobileCats = \Illuminate\Support\Facades\Cache::remember('categories.mobile', now()->addHours(24), function () {
                            return \App\Models\Category::whereNull('parent_id')->where('is_active', true)->limit(6)->get(['_id', 'name', 'slug'])->toArray();
                        });
                    @endphp
                    @foreach($mobileCats as $category)
                        <li><a href="{{ route('category.show', $category['slug']) }}" class="block px-4 py-2 rounded hover:bg-[var(--color-paper-dim)]">{{ $category['name'] }}</a></li>
                    @endforeach
                </ul>
                <div class="mt-6 pt-6 border-t hairline">
                    @auth
                        @if(!(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Admin') || auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin')))
                            <a href="{{ route('customer.dashboard') }}" class="block px-4 py-2 rounded hover:bg-[var(--color-paper-dim)]">My Account</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 rounded hover:bg-[var(--color-paper-dim)]">Logout</button>
                            </form>
                        @else
                            <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 rounded hover:bg-[var(--color-paper-dim)] text-[var(--color-red)] font-medium">Admin Panel</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 rounded hover:bg-[var(--color-paper-dim)]">Logout</button>
                            </form>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="block px-4 py-2 rounded hover:bg-[var(--color-paper-dim)]">Login</a>
                        <a href="{{ route('register') }}" class="block px-4 py-2 rounded hover:bg-[var(--color-paper-dim)]">Register</a>
                    @endauth
                </div>
            </nav>
        </div>
    </div>

    @php
        $waPhone = preg_replace('/[^0-9]/', '', (string) setting('whatsapp_phone'));
        $waEnabled = (bool) setting('whatsapp_show_on_site');
        $waMessage = (string) setting('whatsapp_message');
        $waUrl = $waEnabled && $waPhone !== ''
            ? 'https://wa.me/' . $waPhone . ($waMessage !== '' ? '?text=' . rawurlencode($waMessage) : '')
            : '';
    @endphp
    @if($waUrl)
        <a href="{{ $waUrl }}" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"
           class="fixed bottom-6 right-6 z-50 w-14 h-14 rounded-full text-white flex items-center justify-center transition-transform hover:scale-110"
           style="background:#25D366;box-shadow:0 6px 20px rgba(37,211,102,0.45);font-size:28px;">
            <i class="fa-brands fa-whatsapp"></i>
        </a>
    @endif

    @stack('scripts')
</body>
</html>
