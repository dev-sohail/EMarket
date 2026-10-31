<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

trait PrefixedId
{
    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            $keyName = $model->getKeyName();

            if (empty($model->getAttribute($keyName))) {
                $model->setAttribute($keyName, $model->generatePrefixedId());
            }
        });
    }

    /**
     * Route parameters resolve on the model's own key, which is `_id` for every
     * prefixed model. Returning `getKeyName()` rather than a literal keeps the
     * route key, the generator and `save()` in agreement if a model ever
     * declares a different `$primaryKey`.
     */
    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Alias `id` onto the prefixed key.
     *
     * Contract: `$model->id` returns the same prefixed string as `$model->_id`
     * and `$model->getKey()`. It is a plain accessor and nothing more — it does
     * not create an `id` column in generated queries, it is not appended to
     * `toArray()`/`toJson()` (there is no `$appends` entry, so serialised
     * payloads contain `_id` only), and it is not usable as a counter column.
     * Code needing the key from an array payload must read `_id` or `getKey()`.
     */
    public function getIdAttribute(): mixed
    {
        // The raw bag is read directly on purpose: routing through
        // getAttribute() would re-enter this accessor.
        return $this->attributes[$this->getKeyName()] ?? $this->attributes['id'] ?? null;
    }

    /**
     * Persist the model, retrying only when its own primary key is already taken.
     *
     * `_id` is the `NOT NULL` primary key, so the database is the authority on
     * whether a generated key is free. Everything else — `customers.email`,
     * `users.phone`, `coupons.code`, `invoice_number` — is rethrown on the first
     * attempt: a genuinely invalid row costs one INSERT instead of five, and the
     * caller receives the real error instead of a masked one.
     */
    public function save(array $options = []): bool
    {
        $maxAttempts = 5;
        $attempt = 1;

        while (true) {
            if (! $this->exists) {
                $this->ensurePrefixedIdIsSet();
            }

            try {
                return parent::save($options);
            } catch (UniqueConstraintViolationException $e) {
                if ($this->exists || $attempt >= $maxAttempts || ! $this->isPrimaryKeyCollision($e)) {
                    throw $e;
                }

                $this->setAttribute($this->getKeyName(), $this->generatePrefixedId());

                $attempt++;
            }
        }
    }

    /**
     * Determine whether a unique-constraint violation came from this model's own
     * single-column primary key.
     *
     * `UniqueConstraintViolationException` exposes the offending unique index
     * (`$e->index`) and its columns (`$e->columns`); both are parsed by the
     * driver-specific connection in `Connection::runQueryCallback()`, so this
     * inspects structured data rather than sniffing the error message.
     *
     * MySQL reports `PRIMARY` as the index name. PostgreSQL names the constraint
     * `<table>_pkey` and SQLite reports no index at all, so the column list is
     * checked as well. SQL Server reports only a generated index name
     * (`PK__table__hash`) with no columns; there the check cannot tell a
     * primary-key collision from any other unique index, so nothing is retried
     * and the violation is rethrown — the safe direction to be wrong in.
     */
    protected function isPrimaryKeyCollision(UniqueConstraintViolationException $e): bool
    {
        if ($e->index !== null && strcasecmp($e->index, 'PRIMARY') === 0) {
            return true;
        }

        // A single-column unique index over exactly the key column is this
        // model's primary key: the 2026_09_20 prefixed-id migration promotes
        // `_id` to the primary key and drops every other unique index on it.
        return $e->columns === [$this->getKeyName()];
    }

    /**
     * Supply the key for an insert whose model events are suppressed.
     *
     * `saveQuietly()`, `createQuietly()`, `pushQuietly()` and
     * `replicateQuietly()` all run through `withoutEvents()`, so the `creating`
     * hook never fires and the insert fails against the `NOT NULL` primary key.
     * Every Eloquent write funnels through `save()`, so filling the key here
     * closes that hole without any call-site change. The attribute is set
     * directly rather than mass assigned, so `Model::unguard()` is irrelevant.
     */
    protected function ensurePrefixedIdIsSet(): void
    {
        $keyName = $this->getKeyName();

        if (empty($this->getAttribute($keyName))) {
            $this->setAttribute($keyName, $this->generatePrefixedId());
        }
    }

    /**
     * Attach a generated prefixed key to every row of a bulk insert.
     *
     * Query-builder writes — `DB::table()->insert()`, `insertOrIgnore()`,
     * `upsert()` and `BelongsToMany::attach()` — bypass model events entirely, so
     * rows written that way must carry their own key. Use this instead of
     * hand-rolling identifiers:
     *
     *     DB::table($product->getTable())->insert(
     *         ProductAttributeValue::withPrefixedIds($rows)
     *     );
     *
     * Rows that already carry a key are left untouched. Generated keys are
     * de-duplicated within the batch, because a batch insert has no retry loop
     * and the primary key constraint is its only backstop.
     *
     * @param  array<int|string, array<string, mixed>>  $rows
     * @return array<int|string, array<string, mixed>>
     */
    public static function withPrefixedIds(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $model = new static;
        $keyName = $model->getKeyName();
        $seen = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException(
                    static::class.'::withPrefixedIds() expects every row to be an array.'
                );
            }

            $existing = $row[$keyName] ?? null;

            if (! empty($existing)) {
                $seen[(string) $existing] = true;

                continue;
            }

            $id = $model->generatePrefixedId();

            // Bounded so a frozen clock (Date::setTestNow()) cannot spin
            // forever; anything that slips through is still rejected by the
            // primary key.
            for ($attempt = 0; $attempt < 100 && isset($seen[$id]); $attempt++) {
                $id = $model->generatePrefixedId();
            }

            $seen[$id] = true;
            $rows[$index][$keyName] = $id;
        }

        return $rows;
    }

    /**
     * Build the prefixed primary key for this model.
     *
     * Uniqueness comes from the primary key constraint, not from a pre-insert
     * probe: `save()` retries a real collision, so no extra SELECT is needed to
     * find one out. That keeps a round trip off the insert path of every model
     * in the application, and a probe could never have been atomic with the
     * insert it was trying to pre-empt anyway.
     */
    protected function generatePrefixedId(): string
    {
        return $this->getIdPrefix().'_'.$this->generateUniqueNumber();
    }

    /**
     * Time of day to the microsecond (8 digits) plus 4 random digits, giving
     * 9,000 candidates per microsecond. Two inserts can only collide if they
     * land in the same microsecond, which the primary key plus the `save()`
     * retry cover, so this performs no I/O.
     */
    protected function generateUniqueNumber(): int
    {
        $timestamp = now()->format('Hisu'); // HHMMSSuuuuuu
        $random = random_int(1000, 9999);

        return (int) ($timestamp.$random);
    }

    abstract protected function getIdPrefix(): string;
}
