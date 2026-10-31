<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Register - {{ config('app.name', '') }}</title>
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
                <h2 class="mt-6 font-display text-2xl font-bold text-[var(--color-ink)]">Create your account</h2>
                <p class="mt-2 text-sm text-[var(--color-steel)]">
                    Already have an account?
                    <a href="{{ route('login') }}" class="font-medium text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Sign in</a>
                </p>
            </div>

            <div class="bg-[var(--color-paper)] border hairline p-8">
                @if(session('error'))
                    <div class="mb-4 p-4 border hairline bg-[var(--color-paper-dim)] text-[var(--color-red)] rounded-sm text-sm">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-4 border hairline bg-[var(--color-paper-dim)] text-[var(--color-red)] rounded-sm text-sm">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-[var(--color-ink)] mb-1">First Name</label>
                            <input type="text" name="first_name" id="first_name" value="{{ old('first_name') }}" required autofocus class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('first_name') border-[var(--color-red)] @enderror" placeholder="John">
                            @error('first_name')
                                <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Last Name</label>
                            <input type="text" name="last_name" id="last_name" value="{{ old('last_name') }}" required class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('last_name') border-[var(--color-red)] @enderror" placeholder="Doe">
                            @error('last_name')
                                <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Email Address</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('email') border-[var(--color-red)] @enderror" placeholder="you@example.com">
                        @error('email')
                            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Phone Number <span class="text-[var(--color-steel)]">(optional)</span></label>
                        <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('phone') border-[var(--color-red)] @enderror" placeholder="+92 3XX XXXXXXX">
                        @error('phone')
                            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Password</label>
                        <input type="password" name="password" id="password" required class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('password') border-[var(--color-red)] @enderror" placeholder="Min. 8 characters">
                        @error('password')
                            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Confirm Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" required class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)]" placeholder="Confirm your password">
                    </div>

                    <button type="submit" class="btn btn-primary w-full">Create Account</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>