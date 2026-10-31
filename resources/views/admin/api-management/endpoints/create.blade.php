@extends('layouts.admin')

@section('title', 'Create Endpoint')

@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Create Endpoint</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">In API definition <span class="font-mono">{{ $definition->slug }}</span></p>
    </div>
    <a href="{{ route('admin.api-management.definitions.edit', $definition->getKey()) }}" class="py-2 px-4 rounded border hairline text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition text-sm">Back to definition</a>
</div>
@endsection

@section('content')
<div class="max-w-2xl">
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <form method="POST" action="{{ route('admin.api-management.endpoints.store', $definition->getKey()) }}">
            @include('admin.api-management.endpoints._form')
        </form>
    </div>
</div>
@endsection