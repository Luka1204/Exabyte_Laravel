<?php

namespace App\Models;

use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'company_id',
        'cash_session_id',
        'user_id',
        'idempotency_key',
        'request_hash',
        'ticket_number',
        'status',
        'voided_by',
        'voided_at',
        'void_reason',
        'subtotal',
        'tax_total',
        'total',
        'cash_received',
        'change_due',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_due' => 'decimal:2',
            'voided_at' => 'immutable_datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
