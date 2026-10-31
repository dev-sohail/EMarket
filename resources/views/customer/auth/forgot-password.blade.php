<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forgot Password - {{ config('app.name', '') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3.17.4/dist/cdn.min.js"></script>
</head>
<body class="font-sans antialiased bg-[var(--color-paper-dim)] text-[var(--color-ink)]">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <a href="{{ route('home') }}">
                    <h1 class="font-display text-3xl font-bold text-[var(--color-red)]">{{ config('app.name', '') }}</h1>
                </a>
                <h2 class="mt-6 font-display text-2xl font-bold text-[var(--color-ink)]">Reset your password</h2>
                <p class="mt-2 text-sm text-[var(--color-steel)]">
                    Enter your email and we'll send you a link to reset your password.
                </p>
            </div>

            <div class="bg-[var(--color-paper)] border hairline p-8">
                @if(session('status'))
                    <div class="mb-4 p-4 border hairline bg-[var(--color-paper-dim)] text-[var(--color-sale)] rounded-sm text-sm">
                        {{ session('status') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-4 border hairline bg-[var(--color-paper-dim)] text-[var(--color-red)] rounded-sm text-sm">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Email Address</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('email') border-[var(--color-red)] @enderror" placeholder="you@example.com">
                        @error('email')
                            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-full">Send Reset Link</button>
                </form>

                <p class="mt-6 text-center text-sm text-[var(--color-steel)]">
                    <a href="{{ route('login') }}" class="font-medium text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Back to sign in</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
