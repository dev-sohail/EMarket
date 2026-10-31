<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with(['order', 'customer'])
            ->latest()
            ->paginate(15);

        return view('admin.invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['order', 'order.customer', 'items']);

        return view('admin.invoices.show', compact('invoice'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,_id',
        ]);

        $order = Order::findOrFail($request->input('order_id'));

        $existingInvoice = Invoice::where('order_id', $order->getKey())->first();
        if ($existingInvoice) {
            return redirect()->route('admin.invoices.show', $existingInvoice)
                ->with('success', 'Invoice already exists for this order.');
        }

        return DB::transaction(function () use ($order) {
            $invoice = Invoice::create([
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
                $invoice->items()->create([
                    'order_item_id' => $item->getKey(),
                    'product_name' => $item->product->name ?? $item->product_name ?? 'Product',
                    'sku' => $item->sku ?? null,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total ?? ($item->quantity * $item->unit_price),
                ]);
            }

            return redirect()->route('admin.invoices.show', $invoice)
                ->with('success', 'Invoice generated successfully.');
        });
    }

    public function download(Invoice $invoice)
    {
        $invoice->load(['order', 'order.customer', 'items']);

        $html = view('admin.invoices.pdf', compact('invoice'))->render();

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', "inline; filename=\"invoice-{$invoice->invoice_number}.html\"");
    }
}
