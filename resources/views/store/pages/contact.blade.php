@extends('layouts.store')

@section('title', 'Contact Us')

@section('content')
{{-- Hero --}}
<section class="bg-[var(--color-ink)] text-white">
    <div class="max-w-7xl mx-auto px-4 py-12 sm:py-16">
        <span class="eyebrow text-[var(--color-red)]">Contact</span>
        <h1 class="font-display text-3xl sm:text-4xl font-bold mt-2 leading-tight">Get in Touch</h1>
        <p class="text-white/70 mt-3 max-w-xl">Have a question or need help? Reach out and our team will get back to you.</p>
    </div>
</section>

<div class="max-w-4xl mx-auto px-4 py-12">
    @if(session('success'))
        <div class="border hairline bg-[var(--color-paper-dim)] p-4 mb-8 flex items-center gap-3">
            <i class="fa-solid fa-check text-lg text-[var(--color-sale)]"></i>
            <p class="text-[var(--color-ink)] text-sm">{{ session('success') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
        <div>
            <h2 class="font-display text-xl font-semibold text-[var(--color-ink)] mb-4">Our Details</h2>
            <p class="text-[var(--color-ink-soft)] mb-6">We're here for you. Reach out to us through any of the following methods.</p>

            <div class="space-y-4">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 border border-[var(--color-red)] flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-phone text-lg text-[var(--color-red)]"></i>
                    </div>
                    <div>
                        <h3 class="font-medium text-[var(--color-ink)]">Phone</h3>
                        <p class="text-[var(--color-ink-soft)]">{{ setting('store_phone') ?? '+1 555 000 0000' }}</p>
                        <p class="text-sm text-[var(--color-steel)]">Support line — calls only</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 border border-[var(--color-red)] flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-envelope text-lg text-[var(--color-red)]"></i>
                    </div>
                    <div>
                        <h3 class="font-medium text-[var(--color-ink)]">Email</h3>
                        <p class="text-[var(--color-ink-soft)]">{{ setting('store_email') ?? 'hello@example.com' }}</p>
                        <p class="text-sm text-[var(--color-steel)]">We reply within 24 hours</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 border border-[var(--color-red)] flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-location-dot text-lg text-[var(--color-red)]"></i>
                    </div>
                    <div>
                        <h3 class="font-medium text-[var(--color-ink)]">Address</h3>
                        <p class="text-[var(--color-ink-soft)]">{{ setting('store_address') ?? '123 Example Street, Example City' }}</p>
                    </div>
                </div>

                <div class="mt-8 p-4 bg-[var(--color-paper-dim)] border border-[var(--color-line)] rounded-lg">
                    <p class="text-[var(--color-ink)] text-sm font-medium mb-2">Please Email Us — We Can\'t Reply to Texts</p>
                    <p class="text-[var(--color-ink-soft)] text-sm">We get a lot of text messages these days, and as a small team we\'ve had to make it official: we\'re not able to accept or reply to them.                     Send us an email at <strong>{{ setting('store_email') ?? 'hello@example.com' }}</strong> (or call the support line above) and we\'ll be glad to help.</p>
                </div>
            </div>
            @php
                $socials = [
                    ['key' => 'facebook_url', 'icon' => 'fa-brands fa-facebook', 'label' => 'Facebook'],
                    ['key' => 'instagram_url', 'icon' => 'fa-brands fa-instagram', 'label' => 'Instagram'],
                    ['key' => 'tiktok_url', 'icon' => 'fa-brands fa-tiktok', 'label' => 'TikTok'],
                    ['key' => 'twitter_url', 'icon' => 'fa-brands fa-x-twitter', 'label' => 'Twitter'],
                    ['key' => 'youtube_url', 'icon' => 'fa-brands fa-youtube', 'label' => 'YouTube'],
                ];
                $socialLinks = array_filter($socials, fn($s) => !empty(setting($s['key'])));
            @endphp
            @if(count($socialLinks) > 0)
            <div class="mt-12 flex gap-6">
                @foreach($socialLinks as $social)
                <a href="{{ setting($social['key']) }}" target="_blank" rel="noopener" title="{{ $social['label'] }}" class="w-12 h-12 border border-[var(--color-red)] flex items-center justify-center flex-shrink-0 hover:bg-[var(--color-red)] hover:text-white transition">
                    <i class="{{ $social['icon'] }} text-lg text-[var(--color-red)] hover:text-white"></i>
                </a>
                @endforeach
            </div>
            @endif
        </div>

        <div>
            <h2 class="font-display text-xl font-semibold text-[var(--color-ink)] mb-4">Send us a Message</h2>
            <form action="{{ route('store.contact.submit') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Your Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('name') border-[var(--color-red)] @enderror" placeholder="John Doe">
                    @error('name')<p class="text-sm text-[var(--color-red)] mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('email') border-[var(--color-red)] @enderror" placeholder="you@example.com">
                    @error('email')<p class="text-sm text-[var(--color-red)] mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Subject</label>
                    <input type="text" name="subject" value="{{ old('subject') }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('subject') border-[var(--color-red)] @enderror" placeholder="How can we help?">
                    @error('subject')<p class="text-sm text-[var(--color-red)] mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Message</label>
                    <textarea name="message" rows="5" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('message') border-[var(--color-red)] @enderror" placeholder="Tell us more...">{{ old('message') }}</textarea>
                    @error('message')<p class="text-sm text-[var(--color-red)] mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-primary w-full">Send Message</button>
            </form>
        </div>
    </div>
</div>
@endsection
