@extends('errors.partials.shell')

@section('status', '405')
@section('title', 'That action is not available here')
@section('path', $path ?? request()->path())

@section('lead', 'The address exists, but it does not accept a '.$verb.' request. Nothing was changed.')

@section('details')
    <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
        <dt class="text-[var(--color-steel)] shrink-0">You requested</dt>
        <dd class="font-mono text-xs text-[var(--color-ink)] text-right break-all">{{ $verb }}</dd>
    </div>
    <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
        <dt class="text-[var(--color-steel)] shrink-0">Address</dt>
        <dd class="font-mono text-xs text-[var(--color-ink)] text-right break-all">{{ $path ?? request()->path() }}</dd>
    </div>
    <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
        <dt class="text-[var(--color-steel)] shrink-0">Accepts</dt>
        <dd class="flex flex-wrap justify-end gap-1.5">
            @forelse($allowedMethods ?? [] as $allowed)
                <span class="inline-flex items-center rounded-md bg-[var(--color-sale)]/10 px-2 py-1 font-mono text-xs font-medium text-[var(--color-sale)]">{{ $allowed }}</span>
            @empty
                <span class="text-xs text-[var(--color-steel)] italic">no verbs reported</span>
            @endforelse
        </dd>
    </div>
@endsection

@section('hint')
    {{--
        Payment methods are a fixed catalog (COD and Online) seeded by
        PaymentMethodSeeder - there is deliberately no create route, so the
        `create` segment falls through to the {paymentMethod} update route and
        arrives here. Saying so beats a bare 405.
    --}}
    @if(($path ?? request()->path()) === 'admin/payment-methods/create')
        <p>
            Payment methods are a <strong>fixed set</strong> &mdash; Cash on Delivery and Online &mdash; seeded by
            <span class="font-mono">PaymentMethodSeeder</span>. There is nothing to create; configure the two that exist instead.
        </p>
    @elseif(($path ?? request()->path()) === 'admin/payment-methods')
        <p>
            The listing is read-only. Open a method's <strong>Edit</strong> page to change its name, instructions or availability.
        </p>
    @else
        <p>
            This usually means a form was submitted twice, or a link was edited by hand.
            The page you want is probably one of the links below.
        </p>
    @endif
@endsection

@section('actions')
    @if(($path ?? request()->path()) === 'admin/payment-methods/create')
        <a href="{{ route('admin.payment-methods.index') }}" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-money-bill-transfer mr-2"></i>Payment Methods
        </a>
    @endif

    @if(request()->is('admin', 'admin/*'))
        <a href="{{ route('admin.dashboard') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
            <i class="fa-solid fa-gauge-high mr-2"></i>Dashboard
        </a>
    @else
        <a href="{{ route('home') }}" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-house mr-2"></i>Back to the shop
        </a>
    @endif
@endsection