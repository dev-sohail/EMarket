@extends('layouts.admin')

@section('title', 'Edit Banner')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Edit Banner</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">
            <span class="font-mono">{{ $banner->getKey() }}</span> · last updated {{ $banner->updated_at?->diffForHumans() }}
        </p>
    </div>
    <a href="{{ route('admin.banners.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Back to Banners</a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.banners.update', $banner->getKey()) }}" enctype="multipart/form-data" id="banner-form">
    @csrf
    @method('PUT')
    @include('admin.banners._form', ['submitLabel' => 'Update Banner'])
</form>

@push('scripts')
@include('admin.banners._form-scripts')
@endpush
@endsection
