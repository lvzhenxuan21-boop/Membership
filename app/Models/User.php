<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'tenant_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, Billable, HasApiTokens, MustVerifyEmailTrait;

    public function canAccessPanel(Panel $panel): bool
    {
        // 平台管理员直接放行；商户角色(tenant_admin/staff)必须归属某个租户才能进后台
        if ($this->hasAnyRole(['super_admin', 'admin'])) return true;
        return $this->tenant_id && $this->hasAnyRole(['tenant_admin', 'staff']);
    }

    public function memberProfiles(): HasMany
    {
        return $this->hasMany(MemberProfile::class);
    }

    public function memberProfile(?int $tenantId = null): HasOne
    {
        $q = $this->hasOne(MemberProfile::class);
        if ($tenantId) $q->where('tenant_id', $tenantId);
        return $q;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function pointLedgers(): HasMany
    {
        return $this->hasMany(PointLedger::class);
    }

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
}
