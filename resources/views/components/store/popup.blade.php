@if(isset($popups) && $popups->isNotEmpty())
@php
    $currentRoute = request()->route()?->getName() ?? '';
    $isProduct = str_contains($currentRoute, 'product');
    $isCategory = str_contains($currentRoute, 'category');
    $isCart = str_contains($currentRoute, 'cart');
    $isCheckout = str_contains($currentRoute, 'checkout');
@endphp
@foreach($popups as $popup)
    @php
        $cookieKey = 'popup_' . $popup->getKey();
        $alreadyShown = request()->cookie($cookieKey) !== null;
        $showOnlyOnceSession = $popup->show_only_once && session('popup_shown_' . $popup->getKey());
    @endphp

    @if(!$alreadyShown && !$showOnlyOnceSession)
    @php
        $matchesPosition = match($popup->position) {
            'homepage' => request()->is('/') || request()->is('/home'),
            'all_pages' => true,
            'product_page' => $isProduct,
            'category_page' => $isCategory,
            'cart' => $isCart,
            'checkout' => $isCheckout,
            default => true,
        };
        // Honour the per-device flags. The composer only requires that the popup
        // targets at least one device, so the matching one is decided here.
        $matchesDevice = is_mobile_request() ? $popup->show_on_mobile : $popup->show_on_desktop;
    @endphp
    @if($matchesPosition && $matchesDevice)
    <div x-data="popup"
    data-cookie-key="{{ $cookieKey }}"
    data-cookie-days="{{ $popup->cookie_expiry_days ?? 7 }}"
    data-delay="{{ ($popup->delay_seconds ?? 0) * 1000 }}"
    data-enable-cookie="{{ $popup->enable_cookie ? '1' : '0' }}"
    data-show-only-once="{{ $popup->show_only_once ? '1' : '0' }}"
    data-popup-id="{{ $popup->getKey() }}"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[50] flex items-center justify-center"
    style="background-color: {{ sanitize_color($popup->overlay_color, '#000000') }};">
        <div class="w-full {{ $popup->size === 'small' ? 'max-w-md' : ($popup->size === 'large' ? 'max-w-5xl' : 'max-w-2xl') }} mx-4">
            <div class="rounded-lg overflow-hidden shadow-xl"
                 style="background-color: {{ sanitize_color($popup->color, 'auto') }};">

                @if($popup->type === 'custom_html')
                    <div class="p-6">
                        {!! \App\Rules\SafeHtml::sanitize($popup->custom_html, \App\Rules\SafeHtml::PROFILE_POPUP) !!}
                    </div>
                @else
                    <div class="flex {{ $popup->image ? 'md:flex-row' : 'flex-col' }} items-center gap-0">
                        @if($popup->image)
                            <div class="md:w-1/2">
                                <img src="{{ asset('storage/' . $popup->image) }}" alt="{{ $popup->title }}" class="w-full h-full object-cover">
                            </div>
                        @endif
                        <div class="p-6 {{ $popup->image ? 'md:w-1/2' : 'text-center' }} flex-1">
                            @if($popup->subtitle)
                                <span class="eyebrow text-sm text-[var(--color-steel)]">{{ $popup->subtitle }}</span>
                            @endif
                            @if($popup->title)
                                <h3 class="font-display text-3xl font-bold text-[var(--color-ink)] mt-2">{{ $popup->title }}</h3>
                            @endif
                            @if($popup->description)
                                <p class="text-sm text-[var(--color-steel)] mt-3">{{ $popup->description }}</p>
                            @endif
                        </div>
                    </div>
                @endif

                @if($popup->cta_text || $popup->url)
                    <div class="p-4 border-t hairline flex justify-end gap-3">
                        @if($popup->cta_text)
                                                    @if($popup->url)
                                                        <a href="{{ $popup->url }}"
                                                            @if($popup->enable_cookie || $popup->show_only_once)
                                                                @click="dismiss()"
                                                            @endif
                                                            class="btn btn-primary">
                                                            {{ $popup->cta_text }}
                                                        </a>
                                                    @else
                                                        <span class="btn btn-primary">{{ $popup->cta_text }}</span>
                                                    @endif
                        @endif
                    </div>
                @endif

                @if($popup->show_close_button)
                    <button type="button"
                        @click="close()"
                        class="absolute top-3 right-3 text-[var(--color-ink)] hover:text-[var(--color-red)] transition">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                @endif
            </div>
        </div>
    </div>
    @endif
    @endif
@endforeach
@endif