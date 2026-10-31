<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Invoice extends PrefixedModel
{
    use HasFactory;

    protected $table = 'invoices';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'invoice_number',
        'order_id',
        'customer_id',
        'subtotal',
        'discount',
        'shipping_cost',
        'tax',
        'additional_charges',
        'grand_total',
        'payment_method',
        'payment_status',
        'issued_at',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'string',
            'customer_id' => 'string',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'tax' => 'decimal:2',
            'additional_charges' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'inv';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Invoice $invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = 'INV-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
            }
        });

        // No foreign keys or cascades exist in this schema, so deleting an
        // invoice would orphan its line items. Delete them one by one so any
        // model events on the children still fire.
        static::deleting(function (Invoice $invoice): void {
            foreach ($invoice->items()->get() as $item) {
                $item->delete();
            }
        });
    }
}
