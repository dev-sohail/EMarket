@extends('layouts.admin-auth')

@section('title', 'Admin Login')

@section('content')
{{-- The layout owns the page height and centring so the flash/validation
     messages it renders above @yield('content') stay attached to this card. --}}
<div>
        <div class="bg-white rounded-lg border hairline p-8">
            <div class="text-center mb-8">
                <h1 class="font-display text-3xl font-bold text-[var(--color-ink)]">{{ setting('store_name', '') }}</h1>
                <p class="text-[var(--color-steel)] text-sm mt-1">Admin Login</p>
            </div>

            {{-- Flash and validation messages are rendered by the layout
                 (resources/views/layouts/admin-auth.blade.php). They used to be
                 inlined here behind `@if($errors->any())`, which hid the
                 `session('error')` message the login controller sets for bad
                 credentials: no validation errors meant the whole block was
                 skipped and a failed login re-rendered a blank form. --}}

            <form method="POST" action="{{ route('admin.login.store') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Email Address</label>
                        <input type="email" name="email" value="{{ old('email') }}" required class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('email') border-[var(--color-red)] @enderror">
                        @error('email')
                            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Password</label>
                        <input type="password" name="password" required class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('password') border-[var(--color-red)] @enderror">
                        @error('password')
                            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="remember" value="1" class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                            <span class="text-sm text-[var(--color-ink-soft)]">Remember me</span>
                        </label>
                    </div>

                    <button type="submit" class="w-full bg-[var(--color-red)] text-white py-2.5 rounded font-semibold hover:bg-[var(--color-red-dark)] transition">
                        Login as Admin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
