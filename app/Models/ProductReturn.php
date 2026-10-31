<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProductReturn extends PrefixedModel
{
    use HasFactory, SoftDeletes;

    /**
     * How many distinct numbers the generator will try before it widens the
     * random component rather than keep rolling the same six characters.
     */
    protected const RETURN_NUMBER_ATTEMPTS = 5;

    protected $table = 'returns';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'return_number',
        'order_id',
        'customer_id',
        'status',
        'description',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'string',
            'customer_id' => 'string',
            'status' => ReturnStatus::class,
            'deleted_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ProductReturn $return) {
            if (empty($return->return_number)) {
                $return->return_number = static::generateReturnNumber();
            }
        });

        // No foreign keys or cascades exist in this schema, so the child rows
        // would be orphaned by a delete. Delete them one by one so any model
        // events on the children still fire.
        static::deleting(function (ProductReturn $return): void {
            foreach ($return->items()->get() as $item) {
                $item->delete();
            }

            foreach ($return->statusHistories()->get() as $history) {
                $history->delete();
            }
        });
    }

    /**
     * Build a return number in the `RET-{Ymd}-{6 upper-case characters}` form.
     *
     * The probe runs through `withTrashed()` because `returns` is a soft-deleting
     * table and `returns_return_number_unique` is a plain index: a trashed return
     * still holds its number, so a soft-delete-blind probe would keep offering a
     * value that is already taken. Six random characters from a 62-symbol alphabet
     * is roughly 57 billion values per day, so a collision is vanishingly rare —
     * but it used to be fatal, because `PrefixedId::save()` only regenerates the
     * `_id` and the `creating` hook does not re-run once `return_number` is set,
     * so all five of its attempts were identical doomed INSERTs. The number is
     * now re-rolled here, which is the check `PrefixedId::generatePrefixedId()`
     * already performs for keys.
     */
    public static function generateReturnNumber(): string
    {
        $day = now()->format('Ymd');

        for ($attempt = 0; $attempt < static::RETURN_NUMBER_ATTEMPTS; $attempt++) {
            $candidate = 'RET-'.$day.'-'.strtoupper(Str::random(6));

            if (! static::withTrashed()->where('return_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Five consecutive collisions inside one day's key space would mean the
        // random source is not random. Widening it is a strictly larger space,
        // and `returns_return_number_unique` remains the final authority.
        return 'RET-'.$day.'-'.strtoupper(Str::random(16));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id', '_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ReturnStatusHistory::class, 'return_id', '_id');
    }

    protected function getIdPrefix(): string
    {
        return 'ret';
    }
}
