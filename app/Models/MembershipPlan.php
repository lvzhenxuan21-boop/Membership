<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    protected $fillable = ['tenant_id','name','slug','description','price','original_price','currency','billing_cycle','duration_days','trial_days','benefits','is_recommended','is_active','sort_order'];
    protected $casts = ['benefits'=>'array','is_recommended'=>'boolean','is_active'=>'boolean','price'=>'decimal:2','original_price'=>'decimal:2'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function features(): BelongsToMany { return $this->belongsToMany(Feature::class,'feature_plan')->withPivot(['value','quota'])->withTimestamps(); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }

    public function calcEndsAt(?\Carbon\Carbon $start = null): ?\Carbon\Carbon
    {
        $start = $start ?? now();
        return match($this->billing_cycle) {
            'daily' => $start->copy()->addDay(),
            'weekly' => $start->copy()->addWeek(),
            'monthly' => $start->copy()->addMonth(),
            'quarterly' => $start->copy()->addMonths(3),
            'yearly' => $start->copy()->addYear(),
            'fixed' => $this->duration_days ? $start->copy()->addDays($this->duration_days) : null,
            'lifetime' => null,
            default => $start->copy()->addMonth(),
        };
    }
}
