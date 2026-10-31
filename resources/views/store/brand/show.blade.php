@extends('layouts.store')

@section('title', $brand->name)

@section('content')
<div class="max-w-full mx-auto px-4 py-8">
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-indigo-600">Home</a>
        <span>/</span>
        <a href="{{ route('shop') }}" class="hover:text-indigo-600">Shop</a>
        <span>/</span>
        <span class="text-gray-900">{{ $brand->name }}</span>
    </nav>

    {{-- Brand Header --}}
    <div class="bg-gradient-to-r from-gray-800 to-gray-900 rounded-2xl p-8 sm:p-12 text-white mb-8">
        @if($brand->logo)
            <img src="{{ asset('storage/' . $brand->logo) }}" alt="{{ $brand->name }}" class="h-12 mb-4" loading="lazy">
        @endif
        <h1 class="text-3xl sm:text-4xl font-bold mb-2">{{ $brand->name }}</h1>
        @if($brand->description)
            <p class="text-lg text-gray-300 max-w-2xl">{{ $brand->description }}</p>
        @endif
    </div>

    {{-- Sort & Results --}}
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-gray-600">{{ $products->total() ?? count($products) }} products</p>
        <select class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option>Default Sort</option>
            <option>Newest</option>
            <option>Price: Low to High</option>
            <option>Price: High to Low</option>
        </select>
    </div>

    {{-- Products --}}
    @if(isset($products) && count($products))
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
            @foreach($products as $product)
                @include('components.store.product-card', ['product' => $product])
            @endforeach
        </div>
        @if(method_exists($products, 'links'))
            <div class="mt-8">{{ $products->withQueryString()->links() }}</div>
        @endif
    @else
        <div class="text-center py-16">
            <i class="fa-solid fa-box text-5xl text-gray-300"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No products from this brand</h3>
            <a href="{{ route('shop') }}" class="text-indigo-600 hover:text-indigo-700 font-medium">Browse All Products</a>
        </div>
    @endif
</div>
@endsection
