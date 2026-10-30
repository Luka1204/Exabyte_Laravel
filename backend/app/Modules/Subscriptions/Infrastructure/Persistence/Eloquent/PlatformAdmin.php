<?php

namespace App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAdmin extends Model
{
    protected $table = 'platform_admins';

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
