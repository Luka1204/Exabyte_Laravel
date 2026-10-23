<?php

namespace App\Models;

use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
