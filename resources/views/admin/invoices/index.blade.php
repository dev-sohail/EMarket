@extends('layouts.admin')

@section('title', 'Invoices')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Invoices</h1>
</div>
@endsection

@section('content')
<div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Invoice #</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Order</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Total</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Status</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($invoices ?? [] as $invoice)
                    <tr class="hover:bg-[var(--color-paper-dim)]">
                        <td class="px-6 py-4 text-sm font-medium text-[var(--color-ink)]">{{ $invoice->invoice_number }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">#{{ $invoice->order->order_number ?? $invoice->order_id }}</td>
                        <td class="px-6 py-4 text-sm font-medium text-[var(--color-ink)]">{{ format_currency($invoice->grand_total) }}</td>
                        <td class="px-6 py-4">
                            @php
                                $statusColors = [
                                    'paid' => 'bg-[#1B7A43]/10 text-[#1B7A43]',
                                    'pending' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]',
                                    'unpaid' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]',
                                    'cancelled' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]',
                                    'overdue' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]',
                                ];
                                $color = $statusColors[$invoice->payment_status] ?? 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]';
                            @endphp
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $color }}">{{ ucfirst($invoice->payment_status) }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.invoices.show', $invoice->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">View</a>
                                <a href="{{ route('admin.invoices.download', $invoice->getKey()) }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink-soft)] text-sm">Download</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-[var(--color-steel)]">No invoices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
