<header class="bg-[var(--color-paper)] border-b hairline sticky top-0 z-40">
    {{-- Top Bar --}}
    <div class="bg-[var(--color-ink)] text-white text-xs tracking-wide">
        @if(setting('store_top_bar_message'))
            <div class="max-w-full mx-auto px-4 py-2 flex justify-center items-center">
                <span>{{ setting('store_top_bar_message') }}</span>
            </div>
        @endif
    </div>

    {{-- Main Header --}}
    <div class="max-w-full mx-auto px-4 py-4">
        <div class="flex items-center justify-between gap-4">
            {{-- Mobile Menu Button --}}
            <button type="button" data-toggle="mobile-menu" class="lg:hidden p-2 hover:bg-[var(--color-paper-dim)] rounded">
                <i class="fa-solid fa-bars text-2xl"></i>
            </button>

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="flex-shrink-0 group">
                @if(setting('store_logo'))
                    <img src="{{ asset('storage/' . setting('store_logo')) }}" alt="{{ setting('store_name', config('app.name', '')) }}" class="h-10 object-contain">
                @else
                    <span class="font-display text-2xl font-bold tracking-tight text-[var(--color-ink)]">{{ setting('store_name', config('app.name', '')) }}</span>
                    <span class="block h-[3px] w-8 bg-[var(--color-red)] mt-0.5 transition-all group-hover:w-full"></span>
                @endif
            </a>

            {{-- Search Bar --}}
            <form action="{{ route('search') }}" method="GET" class="hidden md:flex flex-1 max-w-xl">
                <div class="flex w-full">
                    <select name="category" class="border border-r-0 hairline rounded-l px-3 py-2 bg-[var(--color-paper-dim)] text-sm focus:outline-none focus:ring-1 focus:ring-[var(--color-red)]">
                        <option value="">All Categories</option>
                        @php
                            $navCats = \Illuminate\Support\Facades\Cache::remember('categories.nav', 1440, function () {
                                return \App\Models\Category::whereNull('parent_id')->where('is_active', true)->get(['_id', 'name', 'slug'])->toArray();
                            });
                            $navCats = is_array($navCats) ? $navCats : ($navCats->toArray() ?? []);
                        @endphp
                        @foreach($navCats as $cat)
                            <option value="{{ $cat['slug'] ?? ($cat->slug ?? '') }}">{{ $cat['name'] ?? ($cat->name ?? '') }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search products..." class="flex-1 border-y hairline px-4 py-2 focus:outline-none focus:ring-1 focus:ring-[var(--color-red)]">
                    <button type="submit" class="bg-[var(--color-ink)] text-white px-6 py-2 rounded-r hover:bg-[var(--color-red)] transition">
                        <i class="fa-solid fa-magnifying-glass text-xl"></i>
                    </button>
                </div>
            </form>

            {{-- Right Actions --}}
            <div class="flex items-center gap-2 sm:gap-4">
                {{-- Wishlist --}}
                @auth
                    @if(!(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Admin') || auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin')))
                        <a href="{{ route('customer.wishlist') }}" class="p-2 hover:bg-[var(--color-paper-dim)] rounded relative hidden sm:block">
                            <i class="fa-solid fa-heart text-2xl"></i>
                        </a>
                    @endif
                @endauth

                {{-- Cart --}}
                <a href="{{ route('cart') }}" class="p-2 hover:bg-[var(--color-paper-dim)] rounded relative">
                    <i class="fa-solid fa-cart-shopping text-2xl"></i>
                    <span data-cart-count class="absolute -top-1 -right-1 bg-[var(--color-red)] text-white text-xs w-5 h-5 rounded-full flex items-center justify-center">0</span>
                </a>

                {{-- User Menu --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="p-2 hover:bg-[var(--color-paper-dim)] rounded">
                        <i class="fa-solid fa-user text-2xl"></i>
                    </button>
                    <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-[var(--color-paper)] rounded shadow-lg border hairline py-1 z-50">
                        @auth
                            @if(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Admin') || auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin'))
                                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-[var(--color-paper-dim)] text-[var(--color-red)] font-medium">Admin Panel</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-[var(--color-paper-dim)]">Logout</button>
                                </form>
                            @else
                                <a href="{{ route('customer.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-[var(--color-paper-dim)]">My Account</a>
                                <a href="{{ route('customer.orders') }}" class="block px-4 py-2 text-sm hover:bg-[var(--color-paper-dim)]">My Orders</a>
                                <a href="{{ route('customer.wishlist') }}" class="block px-4 py-2 text-sm hover:bg-[var(--color-paper-dim)]">Wishlist</a>
                                <hr class="my-1 hairline">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-[var(--color-paper-dim)]">Logout</button>
                                </form>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="block px-4 py-2 text-sm hover:bg-[var(--color-paper-dim)]">Login</a>
                            <a href="{{ route('register') }}" class="block px-4 py-2 text-sm hover:bg-[var(--color-paper-dim)]">Register</a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>

        {{-- Mobile Search --}}
        <form action="{{ route('search') }}" method="GET" class="md:hidden mt-4">
            <div class="flex">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search products..." class="flex-1 border hairline rounded-l px-4 py-2 focus:outline-none focus:ring-1 focus:ring-[var(--color-red)]">
                <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2 rounded-r">
                    <i class="fa-solid fa-magnifying-glass text-lg "></i>
                </button>
            </div>
        </form>
    </div>

    {{-- Category Navigation --}}
    <nav class="border-t hairline hidden lg:block">
        <div class="max-w-full mx-auto px-4">
            <ul class="flex items-center gap-1 overflow-x-auto text-sm">
                <li><a href="{{ route('page.show', 'about-us') }}" class="block px-4 py-3 hover:text-[var(--color-red)] whitespace-nowrap">About Us</a></li>
                <li><a href="{{ route('page.show', 'contact') }}" class="block px-4 py-3 hover:text-[var(--color-red)] whitespace-nowrap">Contact Us</a></li>
                <span class="text-red-500">|</span>
                <li><a href="{{ route('shop') }}" class="block px-4 py-3 hover:text-[var(--color-red)] font-medium whitespace-nowrap">All Products</a></li>
                @foreach($navCats as $category)
                    <li>
                        <a href="{{ route('category.show', $category['slug'] ?? ($category->slug ?? '#')) }}" class="block px-4 py-3 hover:text-[var(--color-red)] whitespace-nowrap">{{ $category['name'] ?? ($category->name ?? '') }}</a>
                    </li>
                @endforeach
                <li><a href="{{ route('shop', ['sort' => 'newest']) }}" class="block px-4 py-3 hover:text-[var(--color-red-dark)] whitespace-nowrap text-[var(--color-red)] font-semibold">New Arrivals</a></li>
            </ul>
        </div>
    </nav>
</header>
