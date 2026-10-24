<?php

namespace App\Modules\Tenancy\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model
{
    protected $fillable = ['name'];

    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'company_memberships'
        )->withPivot('role')->withTimestamps();
    }

}
