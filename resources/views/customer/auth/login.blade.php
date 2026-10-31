<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - {{ config('app.name', '') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3.17.4/dist/cdn.min.js"></script>
    <style>
        :root {
            @if(setting('color_ink')) --color-ink: {{ setting('color_ink') }}; @endif
            @if(setting('color_ink_soft')) --color-ink-soft: {{ setting('color_ink_soft') }}; @endif
            @if(setting('color_paper')) --color-paper: {{ setting('color_paper') }}; @endif
            @if(setting('color_paper_dim')) --color-paper-dim: {{ setting('color_paper_dim') }}; @endif
            @if(setting('color_line')) --color-line: {{ setting('color_line') }}; @endif
            @if(setting('color_steel')) --color-steel: {{ setting('color_steel') }}; @endif
            @if(setting('color_red')) --color-red: {{ setting('color_red') }}; @endif
            @if(setting('color_red_dark')) --color-red-dark: {{ setting('color_red_dark') }}; @endif
            @if(setting('color_sale')) --color-sale: {{ setting('color_sale') }}; @endif
        }
    </style>
</head>
<body class="font-sans antialiased bg-[var(--color-paper-dim)] text-[var(--color-ink)]">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <a href="{{ route('home') }}">
                    <h1 class="font-display text-3xl font-bold text-[var(--color-red)]">{{ config('app.name', '') }}</h1>
                </a>
                <h2 class="mt-6 font-display text-2xl font-bold text-[var(--color-ink)]">Sign in to your account</h2>
                <p class="mt-2 text-sm text-[var(--color-steel)]">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="font-medium text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Create one</a>
                </p>
            </div>

            <div class="bg-[var(--color-paper)] border hairline p-8">
                @if(session('status'))
                    <div class="mb-4 p-4 border hairline bg-[var(--color-paper-dim)] text-[var(--color-sale)] rounded-sm text-sm">
                        {{ session('status') }}
                    </div>
                @endif

                @if(session('success'))
                    <div class="mb-4 p-4 border hairline bg-[var(--color-paper-dim)] text-[var(--color-sale)] rounded-sm text-sm">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 p-4 border hairline bg-[var(--color-paper-dim)] text-[var(--color-red)] rounded-sm text-sm">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-4 border hairline bg-[var(--color-paper-dim)] text-[var(--color-red)] rounded-sm text-sm">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Email Address</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('email') border-[var(--color-red)] @enderror" placeholder="you@example.com">
                        @error('email')
                            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Password</label>
                        <input type="password" name="password" id="password" required class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('password') border-[var(--color-red)] @enderror" placeholder="Your password">
                        @error('password')
                            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="remember" value="1" class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                            <span class="text-sm text-[var(--color-steel)]">Remember me</span>
                        </label>
                        @if(Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Forgot password?</a>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-primary w-full">Sign In</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>