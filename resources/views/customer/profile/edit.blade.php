@extends('layouts.store')

@section('title', 'Edit Profile')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8">
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('home') }}" class="hover:text-[var(--color-red)]">Home</a>
        <span>/</span>
        <a href="{{ route('customer.dashboard') }}" class="hover:text-[var(--color-red)]">Dashboard</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">Edit Profile</span>
    </nav>

    <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Edit Profile</h1>

    <div class="bg-white rounded-lg border hairline p-6">
        <form method="POST" action="{{ route('customer.profile.update') }}">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Full Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', auth()->user()->name) }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('name') border-[var(--color-red)] @enderror">
                    @error('name')
                        <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email', auth()->user()->email) }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('email') border-[var(--color-red)] @enderror">
                    @error('email')
                        <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Phone</label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone', auth()->user()->phone) }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('phone') border-[var(--color-red)] @enderror">
                    @error('phone')
                        <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <hr class="my-6 border-[var(--color-line)]">

            <h3 class="font-medium text-[var(--color-ink)] mb-4">Change Password (optional)</h3>
            <div class="space-y-4">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Current Password</label>
                    <input type="password" name="current_password" id="current_password" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('current_password') border-[var(--color-red)] @enderror">
                    @error('current_password')
                        <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-[var(--color-ink)] mb-1">New Password</label>
                    <input type="password" name="password" id="password" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('password') border-[var(--color-red)] @enderror">
                    @error('password')
                        <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Confirm New Password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)]">
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <a href="{{ route('customer.dashboard') }}" class="px-6 py-2.5 border hairline rounded-lg text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Cancel</a>
                <button type="submit" id="save-profile-btn" class="px-6 py-2.5 bg-[var(--color-red)] text-white rounded-lg hover:bg-[var(--color-red-dark)] transition">
                    <span class="btn-text">Save Changes</span>
                    <span class="btn-loading hidden flex items-center gap-2">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        Saving...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    const saveBtn = document.getElementById('save-profile-btn');
    const form = saveBtn?.closest('form');
    if (saveBtn && form) {
        form.addEventListener('submit', function() {
            if (this.checkValidity()) {
                saveBtn.disabled = true;
                saveBtn.querySelector('.btn-text').classList.add('hidden');
                saveBtn.querySelector('.btn-loading').classList.remove('hidden');
            }
        });
    }
});
</script>
