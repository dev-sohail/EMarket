<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Idempotency ledger for inbound Stripe webhook deliveries.
 *
 * The table is keyed on `_id` (a `varchar(36)` primary key) with a unique
 * index on `event_id`, so every write must go through Eloquent: a
 * query-builder insert bypasses `PrefixedId::booted()` and leaves `_id` null.
 * `processed_at` is the commit marker — it is set in the same transaction as
 * the order mutations, so a non-null value means the event's side effects are
 * durable, and a null value means the delivery was seen but not applied.
 */
class StripeWebhookEvent extends PrefixedModel
{
    use HasFactory;

    protected $table = 'stripe_webhook_events';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'event_id',
        'type',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'swe';
    }

    /**
     * Whether this delivery has already had all of its side effects committed.
     */
    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    /**
     * Get the ledger row for a Stripe event id, creating it on first delivery.
     *
     * `firstOrCreate()` issues a plain SELECT before inserting, so a duplicate
     * delivery would otherwise race past the check; the unique index on
     * `event_id` is the real guarantee, and the builder re-reads the winning
     * row when the insert loses that race.
     */
    public static function record(string $eventId, string $type): self
    {
        return static::query()->firstOrCreate(
            ['event_id' => $eventId],
            ['type' => $type]
        );
    }

    /**
     * Stamp the row as fully applied. Returns false if it already was.
     *
     * Call this inside the transaction that applies the event's side effects:
     * committing the two together is what makes the replay guard sound.
     */
    public function markProcessed(): bool
    {
        if ($this->isProcessed()) {
            return false;
        }

        $this->processed_at = now();

        return $this->save();
    }
}
