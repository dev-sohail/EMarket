<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: sans-serif; margin: 40px; color: #333; }
        .header { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .header h1 { font-size: 24px; margin: 0; }
        .meta { font-size: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; font-size: 14px; }
        th { background: #f5f5f5; font-weight: 600; }
        .text-right { text-align: right; }
        .totals { margin-top: 20px; text-align: right; }
        .totals .row { display: flex; justify-content: flex-end; gap: 40px; padding: 4px 0; }
        .totals .grand { font-size: 18px; font-weight: bold; border-top: 2px solid #333; padding-top: 8px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>INVOICE</h1>
            <p class="meta">{{ $invoice->invoice_number }}</p>
        </div>
        <div style="text-align:right">
            <p class="meta">Date: {{ $invoice->created_at->format('M d, Y') }}</p>
            <p class="meta">Due: {{ $invoice->due_at?->format('M d, Y') ?? 'N/A' }}</p>
            <p class="meta">Status: {{ ucfirst($invoice->payment_status) }}</p>
        </div>
    </div>

    <div style="margin-bottom: 30px;">
        @php
            $customer = $invoice->customer ?? $invoice->order->customer;
            $user = $invoice->order->user;
            $shipping = $invoice->order->shippingAddress;
            $billName = $customer
                ? trim($customer->first_name . ' ' . $customer->last_name)
                : ($user->name ?? $shipping->full_name ?? 'Guest');
            $billEmail = $customer->email ?? $user->email ?? '';
        @endphp
        <strong>Bill To:</strong><br>
        {{ $billName }}<br>
        {{ $billEmail }}
        @if($shipping)
            <br>{{ $shipping->address_line_1 }}
            @if($shipping->address_line_2)<br>{{ $shipping->address_line_2 }}@endif
            <br>{{ $shipping->city }}, {{ $shipping->state }} {{ $shipping->postal_code }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items ?? [] as $item)
                <tr>
                    <td>{{ $item->product_name ?? '-' }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ format_currency($item->unit_price) }}</td>
                    <td class="text-right">{{ format_currency($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="row"><span>Subtotal:</span><span>{{ format_currency($invoice->subtotal ?? 0) }}</span></div>
        @if(($invoice->discount ?? 0) > 0)
            <div class="row"><span>Discount:</span><span>-{{ format_currency($invoice->discount) }}</span></div>
        @endif
        @if(($invoice->tax ?? 0) > 0)
            <div class="row"><span>Tax:</span><span>{{ format_currency($invoice->tax) }}</span></div>
        @endif
        <div class="row grand"><span>Total:</span><span>{{ format_currency($invoice->grand_total ?? 0) }}</span></div>
    </div>
</body>
</html>
