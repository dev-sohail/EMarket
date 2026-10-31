@extends('layouts.store')

@section('title', 'My Addresses')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('home') }}" class="hover:text-[var(--color-red)]">Home</a>
        <span>/</span>
        <a href="{{ route('customer.dashboard') }}" class="hover:text-[var(--color-red)]">Dashboard</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">My Addresses</span>
    </nav>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">My Addresses</h1>
        <a href="{{ route('customer.addresses.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2">
            <i class="fa-solid fa-plus text-base"></i>
            Add New Address
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @forelse($addresses as $address)
            <div class="bg-white rounded-lg border p-6 relative {{ $address->is_default ? 'ring-2 ring-[var(--color-red)]' : '' }}">
                @if($address->is_default)
                    <span class="absolute top-3 right-3 bg-[var(--color-red)]/10 text-[var(--color-red)] text-xs font-medium px-2 py-1 rounded">Default</span>
                @endif
                <h3 class="font-medium text-[var(--color-ink)]">{{ $address->label ?? 'Address' }}</h3>
                <div class="mt-2 text-sm text-[var(--color-steel)] space-y-1">
                    <p>{{ $address->full_name }}</p>
                    <p>{{ $address->address_line_1 }}</p>
                    @if($address->address_line_2)
                        <p>{{ $address->address_line_2 }}</p>
                    @endif
                    <p>{{ $address->city }}, {{ $address->state }} {{ $address->postal_code }}</p>
                    @if($address->phone)
                        <p>Phone: {{ $address->phone }}</p>
                    @endif
                </div>
                <div class="flex gap-2 mt-4">
                    <a href="{{ route('customer.addresses.edit', $address->getRouteKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">Edit</a>
                    <form method="POST" action="{{ route('customer.addresses.destroy', $address->getRouteKey()) }}" data-confirm="Delete this address?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-span-2 text-center py-12 bg-white rounded-lg border">
                <i class="fa-solid fa-location-dot text-5xl text-[var(--color-line)]"></i>
                <h3 class="text-lg font-medium text-[var(--color-ink)] mb-2">No addresses saved</h3>
                <a href="{{ route('customer.addresses.create') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Add your first address</a>
            </div>
        @endforelse
    </div>
</div>
@endsection
