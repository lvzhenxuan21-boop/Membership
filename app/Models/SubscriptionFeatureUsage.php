<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionFeatureUsage extends Model
{
    protected $fillable = ['subscription_id','feature_id','used','quota'];
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function feature(): BelongsTo { return $this->belongsTo(Feature::class); }
}
