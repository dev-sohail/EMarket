@extends('layouts.admin')

@section('title', 'Create API Definition')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Create API Definition</h1>
    <a href="{{ route('admin.api-management.definitions.index') }}" class="py-2 px-4 rounded border hairline text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition text-sm">Back to list</a>
</div>
@endsection

@section('content')
<div class="max-w-3xl space-y-6">
    {{-- Reflection is offered up front rather than as a `source` dropdown, because
         it is a generation step: picking "reflection" on the form would only have
         produced a definition with no endpoints, serving nothing. --}}
    <div class="bg-[var(--color-paper-dim)]/50 border hairline rounded-lg p-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-sm font-medium text-[var(--color-ink)]">Building it from a model instead?</h2>
            <p class="text-xs text-[var(--color-steel)] mt-1">
                Reflection generates the definition, its endpoints and one filter parameter per
                column, from an Eloquent model. No endpoints to write by hand.
            </p>
        </div>
        <a href="{{ route('admin.api-management.definitions.reflect') }}"
           class="py-2 px-4 rounded-lg bg-[var(--color-ink)] text-white text-sm font-medium hover:opacity-90 transition whitespace-nowrap">
            Reflect API from Model
        </a>
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <form method="POST" action="{{ route('admin.api-management.definitions.store') }}">
            @include('admin.api-management.definitions._form')
        </form>
    </div>
</div>
@endsection