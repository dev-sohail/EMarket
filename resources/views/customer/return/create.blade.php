@extends('layouts.store')

@section('title', 'Request Return')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('home') }}" class="hover:text-[var(--color-red)]">Home</a>
        <span>/</span>
        <a href="{{ route('customer.returns') }}" class="hover:text-[var(--color-red)]">Returns</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">New Request</span>
    </nav>

    <h1 class="font-display text-2xl font-bold text-[var(--color-ink)] mb-6">Request Return</h1>

    <div class="bg-white rounded-lg border hairline p-6">
        <h2 class="font-semibold text-[var(--color-ink)] mb-2">Order #{{ $order->order_number }}</h2>
        <p class="text-sm text-[var(--color-steel)] mb-4">{{ $order->items->count() }} items &bull; Total: {{ format_currency($order->grand_total) }}</p>

        <form method="POST" action="{{ route('customer.returns.store') }}">
            @csrf
            <input type="hidden" name="order_id" value="{{ $order->getKey() }}">

            <div class="space-y-3">
                @foreach($order->items as $item)
                    <div class="flex items-center gap-4 p-3 border hairline rounded-lg">
                        <div class="flex-1">
                            <p class="font-medium text-sm text-[var(--color-ink)]">{{ $item->product_name }}</p>
                            <p class="text-xs text-[var(--color-steel)]">SKU: {{ $item->sku ?? 'N/A' }} &bull; Qty: {{ $item->quantity }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-[var(--color-steel)]">Return Qty</label>
                            <input type="hidden" name="items[{{ $loop->index }}][order_item_id]" value="{{ $item->getKey() }}">
                            <input type="number" name="items[{{ $loop->index }}][quantity]" value="{{ $item->quantity }}" min="0" max="{{ $item->quantity }}" class="w-16 border hairline rounded px-2 py-1 text-sm">
                        </div>
                    </div>
                @endforeach
            </div>
            @error('items')
                <p class="mt-3 text-sm text-[var(--color-red)]">{{ $message }}</p>
            @enderror

            <div class="space-y-4 mt-6 pt-6 border-t hairline">
                <div>
                    <label for="return_reason_id" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Reason for Return *</label>
                    <select name="return_reason_id" id="return_reason_id" required class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('return_reason_id') border-[var(--color-red)] @enderror">
                        <option value="">Select a reason</option>
                        @foreach(App\Models\ReturnReason::where('is_active', true)->orderBy('sort_order')->get() as $reason)
                            <option value="{{ $reason->getKey() }}" {{ old('return_reason_id') == $reason->getKey() ? 'selected' : '' }}>{{ $reason->reason }}</option>
                        @endforeach
                    </select>
                    @error('return_reason_id')
                        <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="reason" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Additional Details</label>
                    <textarea name="reason" id="reason" rows="4" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('reason') border-[var(--color-red)] @enderror" placeholder="Please describe the issue in detail...">{{ old('reason') }}</textarea>
                    @error('reason')
                        <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <a href="{{ route('customer.returns') }}" class="px-6 py-2.5 border hairline rounded-lg text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-[var(--color-red)] text-white rounded-lg hover:bg-[var(--color-red-dark)] transition">Submit Request</button>
            </div>
        </form>
    </div>
</div>
@endsection
