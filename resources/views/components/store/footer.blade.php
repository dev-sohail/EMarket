<footer class="bg-[var(--color-ink)] text-white/70">
    <div class="max-w-full mx-auto px-4 py-12">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            {{-- Store Info --}}
            <div>
                <span class="font-display text-white text-lg font-bold">{{ setting('store_name', config('app.name', '')) }}</span>
                <span class="block h-[3px] w-8 bg-[var(--color-red)] mt-1 mb-4"></span>
                <p class="text-sm mb-4">{{ setting('store_tagline', '') }}</p>
                @php $footerPhone = setting('store_phone', '+92 300 1234567'); @endphp
                <div class="flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-phone text-base text-[var(--color-red)]"></i>
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $footerPhone) }}" class="hover:text-white transition">{{ $footerPhone }}</a>
                </div>
            </div>

            {{-- Quick Links --}}
            <div>
                <h4 class="eyebrow mb-4">Quick Links</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('shop') }}" class="hover:text-white transition">Shop</a></li>
                    <li><a href="{{ route('shop', ['sort' => 'newest']) }}" class="hover:text-white transition">New Arrivals</a></li>
                    <li><a href="{{ route('shop', ['sort' => 'best_selling']) }}" class="hover:text-white transition">Best Sellers</a></li>
                    <li><a href="{{ route('affiliate') }}" class="hover:text-white transition">Affiliate Program</a></li>
                    @if(setting('blogs_enabled'))
                        <li><a href="{{ route('blog.index') }}" class="hover:text-white transition">Blog</a></li>
                    @endif
                </ul>
            </div>

            {{-- Customer Service --}}
            <div>
                <h4 class="eyebrow mb-4">Customer Service</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('order.track') }}" class="hover:text-white transition">Track Order</a></li>
                    <li><a href="{{ route('page.show', 'contact') }}" class="hover:text-white transition">Contact Us</a></li>
                    <li><a href="{{ route('faq') }}" class="hover:text-white transition">FAQs</a></li>
                    <!-- <li><a href="{{ route('page.show', 'shipping-policy') }}" class="hover:text-white transition">Shipping & Returns</a></li> -->
                     <li><a href="{{ route('page.show', 'peptide-storage') }}" class="hover:text-white transition">Peptide Storage</a></li>
                      <li><a href="{{ route('page.show', 'return-policy') }}" class="hover:text-white transition">Return Policy</a></li>
                       <li><a href="{{ route('page.show', 'delivery-info') }}" class="hover:text-white transition">Delivery Information</a></li>
                </ul>
            </div>

            {{-- Payment Info.

                 Driven by the payment_methods table, not hardcoded. This block
                 previously always advertised COD with a literal "COD" badge,
                 so deactivating the cod method in the admin left the storefront
                 promising a payment option that checkout no longer offers
                 (CheckoutController only offers PaymentMethod::active()). It
                 also interpolated default_country — a two-letter code, hence
                 "across GB" — and implied COD was tied to that country, which
                 nothing enforces. --}}
            <div>
                <h4 class="eyebrow mb-4">Payment Method</h4>

                @php($footerPaymentMethods = \App\Models\PaymentMethod::active()->ordered()->get())

                @if ($footerPaymentMethods->isEmpty())
                    <p class="text-sm text-white/50">No payment method is available online right now. Please contact us to arrange payment.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($footerPaymentMethods as $footerMethod)
                            <li>
                                <div class="flex items-center gap-2">
                                    <div class="bg-white/10 rounded px-3 py-1.5 text-xs font-semibold text-white border border-white/10">
                                        {{ $footerMethod->is_cod ? 'COD' : \Illuminate\Support\Str::upper($footerMethod->name) }}
                                    </div>
                                    <span class="text-xs text-white/50">{{ \Illuminate\Support\Str::limit($footerMethod->description, 40) }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- Bottom Bar --}}
    <div class="border-t border-white/10">
        <div class="max-w-full mx-auto px-4 py-4 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <div class="flex items-center gap-4">
                <a href="{{ route('page.show', 'privacy-policy') }}" class="hover:text-white transition">Privacy Policy</a>
                <a href="{{ route('page.show', 'terms') }}" class="hover:text-white transition">Terms of Service</a>
                <!-- <a href="{{ route('page.show', 'refund-policy') }}" class="hover:text-white transition">Refund Policy</a> -->
            </div>
        </div>
    </div>
</footer>
