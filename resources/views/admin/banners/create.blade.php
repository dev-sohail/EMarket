@extends('layouts.admin')

@section('title', 'Create Banner')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Create Banner</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Pick a position first — it decides which fields the storefront renders.</p>
    </div>
    <a href="{{ route('admin.banners.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Back to Banners</a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data" id="banner-form">
    @csrf
    @include('admin.banners._form', ['submitLabel' => 'Create Banner'])
</form>

@push('scripts')
@include('admin.banners._form-scripts')
@endpush
@endsection
