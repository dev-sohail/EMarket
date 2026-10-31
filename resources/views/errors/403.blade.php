@extends('errors.partials.shell')

@section('status', '403')
@section('title', 'You do not have access to this page')
@section('path', $path ?? request()->path())

@section('lead', 'Your account is signed in, but the role it holds does not grant the permission this screen requires.')

@section('details')
    <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
        <dt class="text-[var(--color-steel)] shrink-0">Address</dt>
        <dd class="font-mono text-xs text-[var(--color-ink)] text-right break-all">{{ $path ?? request()->path() }}</dd>
    </div>
    @if(filled($missingPermission ?? null))
        <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
            <dt class="text-[var(--color-steel)] shrink-0">Required</dt>
            <dd><span class="inline-flex items-center rounded-md bg-[var(--color-paper-dim)] px-2 py-1 font-mono text-xs font-medium text-[var(--color-ink-soft)]">{{ $missingPermission }}</span></dd>
        </div>
    @endif
@endsection

@section('hint')
    {{--
        Controller::authorizeAdmin() aborts with a message naming the permission
        in single quotes. Only that exact shape is surfaced as a chip: an
        arbitrary 403 message could contain anything, so anything unrecognised
        falls back to the generic wording below.
    --}}
    @if(filled($missingPermission ?? null))
        <p>
            Ask an administrator to add <strong>{{ $missingPermission }}</strong> to your role, then reload this page.
            Nothing was changed.
        </p>
    @else
        <p>
            If you believe you should have access, ask an administrator to review the permissions on your role.
            Nothing was changed.
        </p>
    @endif
@endsection

@section('actions')
    @if(request()->is('admin', 'admin/*'))
        <a href="{{ route('admin.dashboard') }}" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-gauge-high mr-2"></i>Dashboard
        </a>
        @auth('admin')
            <a href="{{ route('admin.users.index') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
                <i class="fa-solid fa-users mr-2"></i>Users &amp; Roles
            </a>
        @endauth
    @else
        <a href="{{ route('home') }}" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-house mr-2"></i>Back to the shop
        </a>
    @endif
@endsection