<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = ['tenant_id','user_id','membership_plan_id','order_no','status','trial_ends_at','starts_at','ends_at','cancelled_at','payment_method','payment_id','payment_order_no','paid_amount','meta'];
    protected $casts = ['trial_ends_at'=>'datetime','starts_at'=>'datetime','ends_at'=>'datetime','cancelled_at'=>'datetime','meta'=>'array','paid_amount'=>'decimal:2'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function plan(): BelongsTo { return $this->belongsTo(MembershipPlan::class, 'membership_plan_id'); }
    public function featureUsages(): HasMany { return $this->hasMany(SubscriptionFeatureUsage::class); }

    public function isActive(): bool
    {
        return $this->status==='active' && ($this->ends_at===null || $this->ends_at->isFuture());
    }

    public function canConsume(Feature $feature, int $amount=1): bool
    {
        $usage = $this->featureUsages()->where('feature_id',$feature->id)->first();
        if (!$usage) return false;
        if ($usage->quota===null) return true;
        return ($usage->used + $amount) <= $usage->quota;
    }
}
