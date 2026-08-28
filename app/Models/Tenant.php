<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['name','slug','contact_name','contact_phone','status','settings'];
    protected $casts = ['settings'=>'array'];

    public function branches(): HasMany { return $this->hasMany(Branch::class); }
    public function membershipLevels(): HasMany { return $this->hasMany(MembershipLevel::class); }
    public function membershipPlans(): HasMany { return $this->hasMany(MembershipPlan::class); }
    public function features(): HasMany { return $this->hasMany(Feature::class); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
    public function memberProfiles(): HasMany { return $this->hasMany(MemberProfile::class); }
    public function shops(): HasMany { return $this->hasMany(Shop::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
}
