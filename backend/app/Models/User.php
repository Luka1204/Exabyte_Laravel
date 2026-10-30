<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'token_version',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(
            Company::class,
            'company_memberships'
        )->withPivot('role', 'is_active')->withTimestamps();
    }

    public function platformAdmin(): HasOne
    {
        return $this->hasOne(\App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\PlatformAdmin::class);
    }

    public function hasCompanyPermission(int $companyId, string $permission): bool
    {
        $membership = \Illuminate\Support\Facades\DB::table('company_memberships')
            ->where('company_id', $companyId)
            ->where('user_id', $this->getKey())
            ->where('is_active', true)
            ->first(['role', 'permissions']);
        if (! $membership) return false;
        if ($membership->role === 'owner') return true;

        $permissions = $membership->permissions === null
            ? ($membership->role === 'manager'
                ? ['catalog.view', 'catalog.manage', 'inventory.view', 'inventory.manage', 'sales.operate', 'sales.manage', 'reports.view', 'activity.view']
                : ['catalog.view', 'inventory.view', 'sales.operate'])
            : json_decode($membership->permissions, true);

        if (! is_array($permissions)) return false;
        if (in_array($permission, $permissions, true)) return true;
        return ($permission === 'catalog.view' && in_array('catalog.manage', $permissions, true))
            || ($permission === 'inventory.view' && in_array('inventory.manage', $permissions, true))
            || ($permission === 'sales.operate' && in_array('sales.manage', $permissions, true));
    }
}
