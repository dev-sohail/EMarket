@extends('layouts.admin')

@section('title', 'Tax Rules')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Tax Rules</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Rates applied at checkout. Inactive rules are ignored.</p>
    </div>
    <button type="button" data-action="openTaxRuleModal" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center">
        <i class="fa-solid fa-plus mr-2"></i> Add Tax Rule
    </button>
</div>
@endsection

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-[var(--color-paper-dim)] flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-receipt text-[var(--color-steel)]"></i>
        </div>
        <div>
            <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">Total Rules</p>
            <p class="text-xl font-semibold text-[var(--color-ink)]">{{ $stats['total'] ?? 0 }}</p>
        </div>
    </div>
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-[#1B7A43]/10 flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-circle-check text-[#1B7A43]"></i>
        </div>
        <div>
            <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">Active</p>
            <p class="text-xl font-semibold text-[var(--color-ink)]">{{ $stats['active'] ?? 0 }}</p>
        </div>
    </div>
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-[var(--color-paper-dim)] flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-percent text-[var(--color-steel)]"></i>
        </div>
        <div>
            <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">Percentage Based</p>
            <p class="text-xl font-semibold text-[var(--color-ink)]">{{ $stats['percentage'] ?? 0 }}</p>
        </div>
    </div>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <form method="GET" action="{{ route('admin.tax.index') }}" class="p-4 border-b hairline flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[220px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-steel)] text-sm"></i>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search tax rules…" class="w-full border hairline rounded-lg pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
        </div>
        <select name="type" data-auto-submit class="border hairline rounded-lg px-4 py-2.5 text-sm text-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]">
            <option value="">All Types</option>
            <option value="percentage" @selected(request('type') === 'percentage')>Percentage</option>
            <option value="fixed" @selected(request('type') === 'fixed')>Fixed</option>
        </select>
        <select name="status" data-auto-submit class="border hairline rounded-lg px-4 py-2.5 text-sm text-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]">
            <option value="">All Statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">Filter</button>
        @if(request()->hasAny(['q', 'type', 'status']))
            <a href="{{ route('admin.tax.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Clear</a>
        @endif
    </form>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Rule</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Rate</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Type</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Scope</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($taxRules ?? [] as $taxRule)
                    @php
                        $displayRate = rtrim(rtrim(number_format((float) $taxRule->rate, 2, '.', ''), '0'), '.');
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-[var(--color-paper-dim)] rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fa-solid fa-percent text-[var(--color-steel)]"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-[var(--color-ink)]">{{ $taxRule->name }}</p>
                                    <p class="text-xs text-[var(--color-steel)] font-mono">{{ $taxRule->getKey() }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-semibold text-[var(--color-ink)]">
                                {{ $taxRule->type === 'fixed' ? format_currency((float) $taxRule->rate) : $displayRate.'%' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]">{{ ucfirst($taxRule->type) }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">
                            {{ ($taxRule->targets_count ?? 0) > 0 ? $taxRule->targets_count.' target(s)' : 'Applies to all' }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full {{ $taxRule->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $taxRule->is_active ? 'bg-[#1B7A43]' : 'bg-[var(--color-steel)]' }}"></span>
                                {{ $taxRule->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <button type="button"
                                        data-action="openTaxRuleModal"
                                        {{-- Plain json_encode(), not @json(): the Blade directive stops at the first
     ")" inside $taxRule->getKey() and emits unparseable PHP. --}}
                                        data-args='{{ json_encode([$taxRule->getKey(), $taxRule->name, $displayRate, $taxRule->type, $taxRule->is_active ? 1 : 0], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}'
                                        class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</button>
                                <form method="POST" action="{{ route('admin.tax.toggleStatus', $taxRule->getKey()) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">{{ $taxRule->is_active ? 'Disable' : 'Enable' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.tax.destroy', $taxRule->getKey()) }}" data-confirm="Delete &quot;{{ $taxRule->name }}&quot;? This cannot be undone.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-solid fa-receipt text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">No tax rules found</p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">
                                @if(request()->hasAny(['q', 'type', 'status']))
                                    Try clearing the filters, or
                                @else
                                    Add your first rule to start applying tax at checkout, or
                                @endif
                                <button type="button" data-action="openTaxRuleModal" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">create a tax rule</button>.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(isset($taxRules) && method_exists($taxRules, 'links'))
        <div class="p-4 border-t hairline">{{ $taxRules->links() }}</div>
    @endif
</div>

@include('admin.tax.partials.form-modal')
@endsection