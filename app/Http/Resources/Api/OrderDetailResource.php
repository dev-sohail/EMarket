<?php

namespace App\Http\Resources\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything an admin screen needs to service one order.
 *
 * Line items are stored with a denormalised `product_name`/`sku` snapshot, so
 * an order stays readable after a product is renamed or deleted — the snapshot
 * is what gets rendered, not the live product relation.
 *
 * @property-read Order $resource
 */
class OrderDetailResource extends JsonResource
{
    /**
     * Relations the service must eager-load for this shape to be complete.
     *
     * @var list<string>
     */
    public const RELATIONS = [
        'items.product',
        'addresses',
        'statusHistories',
        'customer',
    ];

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
            'coupon_code' => $this->resource->coupon_code,
            'notes' => $this->resource->notes,
            'tracking_number' => $this->resource->tracking_number,
            'carrier' => $this->resource->carrier,
            'totals' => [
                'subtotal' => (float) $this->resource->subtotal,
                'discount' => (float) $this->resource->discount,
                'shipping' => (float) $this->resource->shipping_cost,
                'tax' => (float) $this->resource->tax,
                'additional_charges' => (float) $this->resource->additional_charges,
                'grand_total' => (float) $this->resource->grand_total,
            ],
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->resource->customer?->getKey(),
                'full_name' => $this->resource->customer?->full_name,
                'email' => $this->resource->customer?->email,
                'phone' => $this->resource->customer?->phone,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->resource->items->map(fn ($item) => [
                'id' => $item->getKey(),
                'product_id' => $item->product_id,
                'product_slug' => $item->product?->slug,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'variant_name' => $item->variant_name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
                'tax' => (float) $item->tax,
                'line_total' => (float) $item->line_total,
            ])),
            'addresses' => $this->whenLoaded('addresses', fn () => $this->resource->addresses->map(fn ($address) => [
                'type' => $address->type,
                'full_name' => $address->full_name,
                'phone' => $address->phone,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
            ])),
            'status_history' => $this->whenLoaded('statusHistories', fn () => $this->resource->statusHistories
                ->sortBy('created_at')
                ->values()
                ->map(fn ($history) => [
                    'from' => $history->from_status,
                    'to' => $history->to_status,
                    'note' => $history->note,
                    'at' => $history->created_at?->toIso8601String(),
                ])),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
