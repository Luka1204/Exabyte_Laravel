<?php

namespace App\Models;

use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public $timestamps = false;

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_SALE = 'sale';

    public const TYPE_SALE_VOID = 'sale_void';

    public const TYPE_SALE_RETURN = 'sale_return';

    protected $fillable = [
        'company_id',
        'product_id',
        'user_id',
        'sale_id',
        'type',
        'quantity_delta',
        'balance_after',
        'reason',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'balance_after' => 'decimal:3',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
