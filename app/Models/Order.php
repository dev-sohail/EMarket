<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class Order extends PrefixedModel
{
    use HasFactory, SoftDeletes;

    /**
     * How many distinct numbers a single call may try before it hands the
     * problem to the insert, and how many numbers the insert itself may be
     * retried with before the violation is rethrown.
     *
     * Both loops advance a monotonically increasing candidate, so a candidate
     * is only ever skipped when something is already known to hold it.
     */
    protected const ORDER_NUMBER_ATTEMPTS = 5;

    protected $table = 'orders';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $hidden = [
        'stripe_client_secret',
        'stripe_payment_intent_id',
        'tracking_token',
        'idempotency_key',
    ];

    protected $fillable = [
        'order_number',
        'cart_id',
        'idempotency_key',
        'tracking_token',
        'customer_id',
        'user_id',
        'status',
        'payment_status',
        'payment_method',
        'currency',
        'subtotal',
        'discount',
        'shipping_cost',
        'tax',
        'additional_charges',
        'grand_total',
        'notes',
        'coupon_code',
        'tracking_number',
        'carrier',
        'fulfillment_status',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'tax' => 'decimal:2',
            'additional_charges' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'deleted_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }

            if (empty($order->tracking_token)) {
                $order->tracking_token = hash('sha256', $order->order_number.'-'.Str::uuid());
            }
        });
    }

    /**
     * Build the next order number for the current year.
     *
     * `orders.order_number` is a PLAIN unique index and an order number is a
     * public identifier — it appears in customer URLs, in admin screens and in
     * API responses — so the index is deliberately left plain and the sequence
     * is instead made total over *every* row:
     *
     *  - The scan runs through `withTrashed()`. A soft-deleted order still
     *    occupies its number, so reading only live rows makes the generator
     *    reissue a number that already exists and the insert dies on
     *    `orders_order_number_unique`.
     *  - The scan is ordered by the *width* of the number first and only then
     *    by the number itself. `%06d` is a minimum width, so a year that passes
     *    999,999 orders starts emitting seven-digit numbers, and a plain string
     *    sort puts `ORD-2026-999999` above `ORD-2026-1000000`. Ordering on
     *    `LENGTH()` first makes the two sort keys agree with the numeric order
     *    for every width. `LENGTH()` is portable across MySQL, SQLite and
     *    PostgreSQL; a driver-specific `CAST(... AS UNSIGNED)` is not.
     *  - The sequence is parsed off the *prefix* rather than the last six
     *    characters, so a seven-digit number no longer parses as zero.
     *
     * Concurrency is handled by `save()` below rather than by a row lock: a
     * `SELECT … FOR UPDATE … LIMIT 1` locks nothing when the year has no orders
     * yet, so the first two orders of a January would otherwise both be handed
     * `ORD-<year>-000001`. Letting the unique index arbitrate and re-deriving
     * the number on the resulting violation is correct on every driver,
     * including the SQLite the test suite runs on and where `FOR UPDATE` is a
     * no-op anyway.
     */
    public static function generateOrderNumber(): string
    {
        $year = date('Y');
        $lastSequence = static::lastSequenceForYear($year);

        for ($attempt = 0; $attempt < static::ORDER_NUMBER_ATTEMPTS; $attempt++) {
            $candidate = static::formatOrderNumber($year, $lastSequence + 1 + $attempt);

            if (! static::withTrashed()->where('order_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Unreachable in practice: the loop only advances past a number that is
        // already taken, and the save-level retry re-derives from the database.
        return static::formatOrderNumber($year, $lastSequence + 1);
    }

    /**
     * Format an order number.
     *
     * `%06d` is a minimum width, not a maximum: numbers below one million keep
     * the six-digit zero-padded form they have always had, and a year that
     * passes a million simply grows to seven digits. The format of every number
     * this method has ever produced is therefore unchanged — only the parsing
     * of it, in `lastSequenceForYear()`, had to become width-agnostic.
     */
    protected static function formatOrderNumber(string $year, int $sequence): string
    {
        return sprintf('ORD-%s-%06d', $year, $sequence);
    }

    /**
     * The highest sequence number issued for a year, trashed rows included.
     */
    protected static function lastSequenceForYear(string $year): int
    {
        $prefix = 'ORD-'.$year.'-';
        $column = (new static)->getConnection()->getQueryGrammar()->wrap('order_number');

        $lastOrderNumber = static::withTrashed()
            ->where('order_number', 'like', $prefix.'%')
            ->orderByRaw('LENGTH('.$column.') desc')
            ->orderByDesc('order_number')
            ->value('order_number');

        if ($lastOrderNumber === null) {
            return 0;
        }

        $sequence = Str::after((string) $lastOrderNumber, $prefix);

        return ctype_digit($sequence) ? (int) $sequence : 0;
    }

    /**
     * Persist the order, re-deriving the order number if it lost a race.
     *
     * `PrefixedId::save()` deliberately rethrows anything that is not a primary
     * key collision, so a duplicate `order_number` used to reach the operator
     * as an opaque 500 after five identical doomed INSERTs. Updates are passed
     * straight through: only the generator path can produce a fresh number, and
     * this also keeps `SoftDeletes::runSoftDelete()` and `restore()` — both of
     * which call `save()` — off the retry path entirely.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            return parent::save($options);
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return parent::save($options);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= static::ORDER_NUMBER_ATTEMPTS || ! $this->orderNumberIsTaken()) {
                    throw $e;
                }

                $this->setAttribute('order_number', static::generateOrderNumber());
            }
        }
    }

    /**
     * Whether this order's number is already held by some row, trashed or not.
     *
     * Decided by re-reading the table rather than by inspecting the driver's
     * index name on the exception, because that metadata is not populated
     * uniformly across drivers and a misread here would either swallow a real
     * error or spin on one that is not ours.
     */
    protected function orderNumberIsTaken(): bool
    {
        $orderNumber = $this->getAttribute('order_number');

        return ! empty($orderNumber)
            && static::withTrashed()->where('order_number', $orderNumber)->exists();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function paymentMethodDefinition(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method', 'code');
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return $this->paymentMethodDefinition?->name ?? match ($this->payment_method) {
            'cod', 'cash_on_delivery' => 'Cash on Delivery',
            null, '' => 'N/A',
            default => Str::headline($this->payment_method),
        };
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    public function shippingAddress()
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'shipping');
    }

    public function billingAddress()
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'billing');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(OrderCharge::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ProductReturn::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            OrderStatus::Cancelled->value,
            OrderStatus::Completed->value,
            OrderStatus::Returned->value,
        ]);
    }

    public function scopeByStatus($query, OrderStatus|string $status): Builder
    {
        if ($status instanceof OrderStatus) {
            $status = $status->value;
        }

        return $query->where('status', $status);
    }

    protected function getIdPrefix(): string
    {
        return 'ord';
    }

    protected static function booted(): void
    {
        static::deleting(function (self $order): void {
            // Delete all related order records individually to fire any model events
            foreach ($order->items()->get() as $item) {
                $item->delete();
            }

            foreach ($order->addresses()->get() as $address) {
                $address->delete();
            }

            foreach ($order->charges()->get() as $charge) {
                $charge->delete();
            }

            foreach ($order->statusHistories()->get() as $history) {
                $history->delete();
            }

            foreach ($order->invoices()->get() as $invoice) {
                $invoice->delete();
            }

            foreach ($order->returns()->get() as $return) {
                $return->delete();
            }
        });
    }
}
