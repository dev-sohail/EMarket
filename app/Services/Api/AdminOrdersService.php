<?php

namespace App\Services\Api;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Resources\Api\OrderDetailResource;
use App\Http\Resources\Api\OrderSummaryResource;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Order administration served through the dynamic API.
 *
 * Auth is not re-implemented here. ApiAuthAndAuthorization has already proved
 * the caller holds a valid token for a `requires_auth` endpoint, and
 * ApiDynamicController has already cleared the endpoint's RBAC/ABAC grant —
 * duplicating either check would create a second place to forget it. This
 * service is reached only after both have passed.
 *
 * Soft-deleted orders stay hidden: they are excluded from every read so a
 * retired order cannot resurface through the admin API.
 */
class AdminOrdersService
{
    /**
     * Hard ceiling on `per_page`, for the same reason the catalogue has one.
     */
    protected const MAX_PER_PAGE = 100;

    /**
     * GET admin/orders — filterable, paginated order list.
     *
     * Pagination metadata is returned explicitly for the same reason as the
     * catalogue listing: the controller's `data`/`meta` envelope flattens a
     * ResourceCollection and would otherwise drop the page counters.
     *
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function orders(Request $request): array
    {
        $validated = $this->validated($request);

        $query = Order::query()->with('customer')->withCount('items');

        $this->applyFilters($query, $validated);

        $paginator = $query
            ->orderByDesc('created_at')
            ->paginate($this->perPage($validated['per_page'] ?? null));

        return [
            'items' => OrderSummaryResource::collection($paginator)->resolve($request),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
        ];
    }

    /**
     * GET admin/orders/{order} — complete order record.
     *
     * Resolved by prefixed id or by order number, because an admin pasting a
     * number from an email is the common case and the two are both stable.
     *
     *
     * @throws NotFoundHttpException
     */
    public function order(Request $request): OrderDetailResource
    {
        $validated = $this->validated($request);
        $identifier = (string) ($validated['order'] ?? '');

        $order = Order::query()
            ->with(OrderDetailResource::RELATIONS)
            ->where(function (Builder $query) use ($identifier) {
                $query->where('_id', $identifier)->orWhere('order_number', $identifier);
            })
            ->first();

        if ($order === null) {
            throw new NotFoundHttpException('Order not found.');
        }

        return new OrderDetailResource($order);
    }

    /**
     * Apply the declared filter parameters to the order query.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function applyFilters(Builder $query, array $validated): void
    {
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['payment_status'])) {
            $query->where('payment_status', $validated['payment_status']);
        }

        if (! empty($validated['fulfillment_status'])) {
            $query->where('fulfillment_status', $validated['fulfillment_status']);
        }

        if (! empty($validated['search'])) {
            $search = (string) $validated['search'];

            // Scoped to the customer's own fields rather than a wildcard join:
            // matching order_number, email and phone in one place is what an
            // admin actually searches for, and it keeps the query indexable.
            $query->where(function (Builder $sub) use ($search) {
                $sub->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $customer) use ($search) {
                        $customer->where('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($validated['placed_from'])) {
            $query->where('created_at', '>=', $validated['placed_from']);
        }

        if (! empty($validated['placed_to'])) {
            $query->where('created_at', '<=', $validated['placed_to']);
        }
    }

    /**
     * Clamp `per_page` into 1..MAX_PER_PAGE.
     */
    protected function perPage(mixed $requested): int
    {
        $perPage = (int) ($requested ?: 25);

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * The enum-backed values an admin filter may use.
     *
     * Exposed so the endpoint's validation rules and this service cannot
     * disagree about what a legal status value is.
     *
     * @return array<string, list<string>>
     */
    public static function allowedFilterValues(): array
    {
        return [
            'status' => Arr::map(OrderStatus::cases(), fn (OrderStatus $case) => $case->value),
            'payment_status' => Arr::map(PaymentStatus::cases(), fn (PaymentStatus $case) => $case->value),
            'fulfillment_status' => Arr::map(FulfillmentStatus::cases(), fn (FulfillmentStatus $case) => $case->value),
        ];
    }

    /**
     * Read the parameters ApiDynamicController validated for this request.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return (array) $request->attributes->get('api_validated', []);
    }
}
