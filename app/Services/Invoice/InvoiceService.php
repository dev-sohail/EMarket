<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        protected Invoice $invoice
    ) {}

    public function generate(string $orderId): Invoice
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::with(['items.product', 'items.variant', 'shippingAddress', 'billingAddress'])
                ->findOrFail($orderId);

            $existingInvoice = $this->invoice->where('order_id', $orderId)->first();
            if ($existingInvoice) {
                return $existingInvoice->load('items');
            }

            $invoice = $this->invoice->create([
                'order_id' => $order->getKey(),
                'customer_id' => $order->customer_id,
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'shipping_cost' => $order->shipping_cost,
                'tax' => $order->tax,
                'additional_charges' => $order->additional_charges,
                'grand_total' => $order->grand_total,
                'payment_status' => 'pending',
                'due_at' => now()->addDays(30),
            ]);

            foreach ($order->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->getKey(),
                    'order_item_id' => $item->getKey(),
                    'product_name' => $item->product->name ?? $item->product_name ?? 'Product',
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total ?? ($item->quantity * $item->unit_price),
                ]);
            }

            return $invoice->load('items');
        });
    }

    public function getByOrder(string $orderId): ?Invoice
    {
        return $this->invoice->with(['items', 'order'])
            ->where('order_id', $orderId)
            ->first();
    }

    public function download(string $invoiceId)
    {
        $invoice = $this->invoice->with(['items', 'order.customer', 'order.shippingAddress'])
            ->findOrFail($invoiceId);

        return $invoice;
    }
}
