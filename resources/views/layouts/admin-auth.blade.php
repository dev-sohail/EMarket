<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Login') - {{ config('app.name') }}</title>
    @if(setting('favicon'))
        @php $faviconExt = pathinfo(setting('favicon'), PATHINFO_EXTENSION); @endphp
        <link rel="icon" type="{{ in_array($faviconExt, ['png','jpg','jpeg']) ? 'image/' . ($faviconExt === 'jpg' ? 'jpeg' : $faviconExt) : 'image/x-icon' }}" href="{{ asset('storage/' . setting('favicon')) }}">
    @endif
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3.17.4/dist/cdn.min.js"></script>
    @stack('styles')
</head>
<body class="font-sans antialiased bg-[var(--color-paper)] text-[var(--color-ink)] min-h-screen flex flex-col items-center justify-center px-4">
    {{-- Flash and validation messages. The admin login screen is the only
         consumer of this layout, and it previously rendered no messages at all
         from the layout, which is why a failed login looked blank. The body is a
         centred flex column so the alerts sit directly above the card rather
         than being separated from it by the card's own min-h-screen wrapper. --}}
    <div class="w-full max-w-md">
        @include('components.admin.alerts')
    </div>

    <div class="w-full max-w-md">
        @yield('content')
    </div>

    @stack('scripts')
</body>
</html>
