{{--
    Shared frame for the branded error pages (404, 405).

    Standalone on purpose: a 404 can be hit by a guest, so this cannot extend
    the admin layout, which assumes an authenticated session and a sidebar.

    Note the CSP in SecurityHeadersMiddleware: style-src allows 'unsafe-inline'
    but script-src does not. No <script> here, and no on* handlers.
--}}
@php
    $isAdmin = request()->is('admin', 'admin/*');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('status') &mdash; @yield('title') - {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/error-page.js'])
</head>
<body class="font-sans antialiased bg-[var(--color-paper)] text-[var(--color-ink)] min-h-screen flex items-center justify-center px-4 py-12">
    <main class="w-full max-w-2xl">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
            <div class="p-8 sm:p-10 text-center border-b hairline">
                <p class="font-display text-6xl font-bold text-[var(--color-steel)]/30 tracking-tight">@yield('status')</p>
                <h1 class="font-display text-2xl font-bold text-[var(--color-ink)] mt-3">@yield('title')</h1>
                <p class="text-sm text-[var(--color-steel)] mt-2 max-w-md mx-auto">@yield('lead')</p>
            </div>

            @hasSection('details')
                <dl class="divide-y divide-[var(--color-line)] text-sm">
                    @yield('details')
                </dl>
            @endif

            @hasSection('hint')
                <div class="px-8 sm:px-10 py-5 bg-[var(--color-paper-dim)] border-b hairline">
                    <div class="flex items-start gap-3">
                        <i class="fa-solid fa-circle-info text-[var(--color-ink-soft)] mt-0.5"></i>
                        <div class="text-sm text-[var(--color-steel)] space-y-1">
                            @yield('hint')
                        </div>
                    </div>
                </div>
            @endif

            <div class="p-8 sm:px-10">
                <div class="flex flex-wrap items-center justify-center gap-3">
                    @yield('actions')
                </div>
            </div>
        </div>

        <p class="text-center text-xs text-[var(--color-steel)] mt-6">
            {{ config('app.name') }} &middot; <span class="font-mono">@yield('path')</span>
        </p>
    </main>
</body>
</html>