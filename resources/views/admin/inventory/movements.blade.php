@extends('layouts.admin')

@section('title', 'Inventory Movements')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Inventory Movements</h1>
    <a href="{{ route('admin.inventory.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Inventory</a>
</div>
@endsection

@section('content')
<div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
    {{-- Filters --}}
    <div class="p-4 border-b bg-[var(--color-paper-dim)]/50">
        <form method="GET" action="{{ route('admin.inventory.movements') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-2xl">
            <div class="sm:col-span-2">
                <select name="type" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent">
                    {{-- The options are the values InventoryMovementType can actually produce:
                     "purchase" was offered here but is not one of them, so that
                     filter could never match a row. --}}
                    <option value="">All Types</option>
                    @foreach(\App\Enums\InventoryMovementType::cases() as $movementType)
                        <option value="{{ $movementType->value }}" @selected(request('type') === $movementType->value)>{{ $movementType->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent">
            </div>
            <div>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent">
            </div>
            <button type="submit" class="sm:col-span-2 bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-1">
                <i class="fa-solid fa-filter"></i> Apply
            </button>
            @if(request()->hasAny(['type', 'date_from', 'date_to']))
                @if(request('type') || request('date_from') || request('date_to'))
                    <a href="{{ route('admin.inventory.movements') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-1">
                        <i class="fa-solid fa-xmark"></i> Clear
                    </a>
                @endif
            @endif
        </form>
    </div>

    {{-- Movements Table --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Type</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Product</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Variant</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Quantity</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Reference</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($movements ?? [] as $movement)
                    @php
                        // The badge branched on a 'purchase' type that nothing
                        // ever writes and printed "Stock_in" for the rest; the
                        // enum carries the label and the colour instead.
                        $movementType = \App\Enums\InventoryMovementType::tryFrom((string) $movement->type);
                        $badgeClasses = match ($movementType?->value) {
                            'sale', 'damage' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]',
                            'stock_in', 'return', 'released' => 'bg-[#1B7A43]/10 text-[#1B7A43]',
                            'adjustment', 'correction', 'reserved' => 'bg-[var(--color-amber)]/10 text-[var(--color-amber-dark)]',
                            default => 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]',
                        };
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)]">
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $badgeClasses }}">{{ $movementType?->label() ?? ucfirst((string) $movement->type) }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $movement->inventory->product->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $movement->inventory->variant ? $movement->inventory->variant->name : '-' }}</td>
                        <td class="px-6 py-4 text-sm font-medium {{ $movement->quantity > 0 ? 'text-[#1B7A43]' : 'text-[var(--color-red)]' }}">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $movement->reference_id ?? $movement->notes ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $movement->created_at->format('M d, Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-[var(--color-steel)]">No movements found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($movements, 'links'))
        <div class="p-4 border-t">{{ $movements->withQueryString()->links() }}</div>
    @endif
</div>
@endsection