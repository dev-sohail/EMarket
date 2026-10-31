<?php

namespace App\Http\Resources\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact order shape for admin list views.
 *
 * Exposes operational state (status, payment, fulfilment, money totals) without
 * the line items, addresses or status trail, which would multiply the payload
 * by an order of magnitude on a page of fifty orders.
 *
 * @property-read Order $resource
 */
class OrderSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'order_number' => $this->resource->order_number,
            'status' => $this->resource->status?->value,
            'payment_status' => $this->resource->payment_status?->value,
            'payment_method' => $this->resource->payment_method,
            'payment_method_label' => $this->resource->payment_method_label,
            'fulfillment_status' => $this->resource->fulfillment_status?->value,
            'currency' => $this->resource->currency,
            'currency_symbol' => currency_symbol(),
            'totals' => [
                'subtotal' => (float) $this->resource->subtotal,
                'discount' => (float) $this->resource->discount,
                'shipping' => (float) $this->resource->shipping_cost,
                'tax' => (float) $this->resource->tax,
                'grand_total' => (float) $this->resource->grand_total,
            ],
            'item_count' => $this->whenCounted('items'),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->resource->customer?->getKey(),
                'full_name' => $this->resource->customer?->full_name,
                'email' => $this->resource->customer?->email,
            ]),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
