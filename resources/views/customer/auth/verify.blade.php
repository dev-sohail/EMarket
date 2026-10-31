@extends('layouts.store')

@section('title', __('Email Verification'))

@section('content')
<div class="max-w-xl mx-auto px-4 py-16">
    <div class="bg-white border hairline rounded-lg shadow-sm">
        <div class="px-6 py-4 border-b hairline">
            <h1 class="font-display font-bold text-xl">{{ __('Email Verification') }}</h1>
        </div>

        <div class="px-6 py-6 space-y-4">
            @if (session('success'))
                <div class="flash-message rounded-md bg-green-50 text-green-800 px-4 py-3 text-sm" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('status') === 'verification-link-sent')
                <div class="flash-message rounded-md bg-green-50 text-green-800 px-4 py-3 text-sm" role="alert">
                    {{ __('A fresh verification link has been sent to your email address.') }}
                </div>
            @endif

            @if (session('error'))
                <div class="flash-message rounded-md bg-red-50 text-red-800 px-4 py-3 text-sm" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <p class="text-[var(--color-steel)]">
                {{ __('Before proceeding, please check your email for a verification link.') }}
            </p>

            <form method="POST" action="{{ route('verification.resend') }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    {{ __('Resend Verification Email') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
