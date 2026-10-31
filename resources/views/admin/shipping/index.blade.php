@extends('layouts.admin')

@section('title', 'Shipping')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Shipping</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Zones decide where a method applies, rules decide what it costs.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.shipping.methods.index') }}" class="bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-line)] transition">Manage Methods</a>
        <a href="{{ route('admin.shipping.zones.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center">
            <i class="fa-solid fa-plus mr-2"></i> Add Zone
        </a>
    </div>
</div>
@endsection

@section('content')
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <a href="{{ route('admin.shipping.zones.index') }}" class="bg-[var(--color-paper)] rounded-lg border hairline p-4 flex items-center gap-3 hover:border-[var(--color-red)] transition group">
        <div class="w-10 h-10 rounded-lg bg-[var(--color-paper-dim)] flex items-center justify-center flex-shrink-0 group-hover:bg-[var(--color-red)]/10">
            <i class="fa-solid fa-earth-americas text-[var(--color-steel)] group-hover:text-[var(--color-red)]"></i>
        </div>
        <div class="min-w-0">
            <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">Zones</p>
            <p class="text-xl font-semibold text-[var(--color-ink)]">{{ $stats['zones'] ?? 0 }}</p>
            <p class="text-xs text-[var(--color-steel)] truncate">{{ $stats['zones_active'] ?? 0 }} active</p>
        </div>
    </a>
    <a href="{{ route('admin.shipping.methods.index') }}" class="bg-[var(--color-paper)] rounded-lg border hairline p-4 flex items-center gap-3 hover:border-[var(--color-red)] transition group">
        <div class="w-10 h-10 rounded-lg bg-[var(--color-paper-dim)] flex items-center justify-center flex-shrink-0 group-hover:bg-[var(--color-red)]/10">
            <i class="fa-solid fa-truck-fast text-[var(--color-steel)] group-hover:text-[var(--color-red)]"></i>
        </div>
        <div class="min-w-0">
            <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">Methods</p>
            <p class="text-xl font-semibold text-[var(--color-ink)]">{{ $stats['methods'] ?? 0 }}</p>
            <p class="text-xs text-[var(--color-steel)] truncate">{{ $stats['methods_active'] ?? 0 }} active</p>
        </div>
    </a>
    <a href="{{ route('admin.shipping.zones.index') }}" class="bg-[var(--color-paper)] rounded-lg border hairline p-4 flex items-center gap-3 hover:border-[var(--color-red)] transition group">
        <div class="w-10 h-10 rounded-lg bg-[#1B7A43]/10 flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-scale-balanced text-[#1B7A43]"></i>
        </div>
        <div class="min-w-0">
            <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">Rules</p>
            <p class="text-xl font-semibold text-[var(--color-ink)]">{{ $stats['rules'] ?? 0 }}</p>
            <p class="text-xs text-[var(--color-steel)] truncate">Across all zones</p>
        </div>
    </a>
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-[var(--color-paper-dim)] flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-circle-info text-[var(--color-steel)]"></i>
        </div>
        <div class="min-w-0">
            <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">Setup</p>
            <p class="text-sm font-semibold text-[var(--color-ink)] mt-1">Zone &rarr; Method &rarr; Rule</p>
            <p class="text-xs text-[var(--color-steel)] truncate">Rules live under each zone</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden flex flex-col">
        <div class="p-4 border-b hairline flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-earth-americas text-[var(--color-red)]"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">Shipping Zones</h2>
            </div>
            <a href="{{ route('admin.shipping.zones.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">Manage</a>
        </div>
        <div class="divide-y divide-[var(--color-line)] flex-1">
            @forelse($zones ?? [] as $zone)
                <div class="p-4 flex items-start justify-between gap-3 hover:bg-[var(--color-paper-dim)] transition-colors">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="text-sm font-medium text-[var(--color-ink)]">{{ $zone->name }}</p>
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full {{ $zone->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $zone->is_active ? 'bg-[#1B7A43]' : 'bg-[var(--color-steel)]' }}"></span>
                                {{ $zone->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <p class="text-xs text-[var(--color-steel)] mt-1 truncate">
                            {{ $zone->cities->count() }} {{ str()->plural('city', $zone->cities->count()) }}
                            &middot; {{ $ruleCount = $zone->rules_count ?? $zone->rules->count() }} {{ str()->plural('rule', $ruleCount) }}
                            @if($zone->rules->isNotEmpty())
                                {{-- price is a decimal:2 cast, so it arrives as a string. Cast to float so the
                                     range is ordered by value rather than by PHP's string
                                     comparison rules. --}}
                                @php $prices = $zone->rules->map(fn ($rule) => (float) $rule->price); @endphp
                                &middot; {{ format_currency($prices->min()) }}&ndash;{{ format_currency($prices->max()) }}
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <a href="{{ route('admin.shipping.zones.edit', $zone->getKey()) }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm" title="Edit zone">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <a href="{{ route('admin.shipping.rules.index', $zone->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">Rules</a>
                    </div>
                </div>
            @empty
                <div class="p-10 text-center">
                    <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                        <i class="fa-solid fa-earth-americas text-[var(--color-steel)]"></i>
                    </div>
                    <p class="text-sm font-medium text-[var(--color-ink)]">No zones yet</p>
                    <p class="text-sm text-[var(--color-steel)] mt-1">
                        <a href="{{ route('admin.shipping.zones.create') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Create your first zone</a> to start assigning cities.
                    </p>
                </div>
            @endforelse
        </div>
        @if(isset($zones) && method_exists($zones, 'links') && $zones->hasPages())
            <div class="p-3 border-t hairline">{{ $zones->links() }}</div>
        @endif
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden flex flex-col">
        <div class="p-4 border-b hairline flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-truck-fast text-[var(--color-red)]"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">Shipping Methods</h2>
            </div>
            <a href="{{ route('admin.shipping.methods.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">Manage</a>
        </div>
        <div class="divide-y divide-[var(--color-line)] flex-1">
            @forelse($methods ?? [] as $method)
                <div class="p-4 flex items-start justify-between gap-3 hover:bg-[var(--color-paper-dim)] transition-colors">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="text-sm font-medium text-[var(--color-ink)]">{{ $method->name }}</p>
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full {{ $method->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $method->is_active ? 'bg-[#1B7A43]' : 'bg-[var(--color-steel)]' }}"></span>
                                {{ $method->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <p class="text-xs text-[var(--color-steel)] mt-1">
                            <span class="price font-medium text-[var(--color-ink-soft)]">{{ format_currency((float) $method->base_cost) }}</span> base
                            @if((float) $method->per_kg_cost > 0)
                                &middot; +{{ format_currency((float) $method->per_kg_cost) }}/kg
                            @endif
                            @if($method->estimated_days)
                                &middot; {{ $method->estimated_days }} {{ str()->plural('day', $method->estimated_days) }}
                            @endif
                            &middot; {{ $method->rules_count ?? $method->rules->count() }} {{ str()->plural('rule', $method->rules_count ?? $method->rules->count()) }}
                        </p>
                    </div>
                    <a href="{{ route('admin.shipping.methods.index') }}#method-{{ $method->getKey() }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm flex-shrink-0" title="Edit method">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                </div>
            @empty
                <div class="p-10 text-center">
                    <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                        <i class="fa-solid fa-truck-fast text-[var(--color-steel)]"></i>
                    </div>
                    <p class="text-sm font-medium text-[var(--color-ink)]">No methods yet</p>
                    <p class="text-sm text-[var(--color-steel)] mt-1">
                        <a href="{{ route('admin.shipping.methods.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Add a shipping method</a> such as Standard or Express.
                    </p>
                </div>
            @endforelse
        </div>
        @if(isset($methods) && method_exists($methods, 'links') && $methods->hasPages())
            <div class="p-3 border-t hairline">{{ $methods->links() }}</div>
        @endif
    </div>
</div>

<div class="mt-6 bg-[var(--color-paper)] rounded-lg border hairline p-5">
    <h3 class="text-sm font-semibold text-[var(--color-ink)] mb-1">How shipping is resolved</h3>
    <p class="text-sm text-[var(--color-steel)]">
        A customer's city is matched to a zone. The active rules inside that zone are then matched against the
        cart's weight, quantity and order value, and the first matching rule sets the price. A rule with a
        <span class="font-medium text-[var(--color-ink-soft)]">free shipping threshold</span> returns zero once the order value reaches it.
        Zones or methods marked inactive are ignored entirely.
    </p>
</div>
@endsection