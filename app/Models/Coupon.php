<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = ['tenant_id','name','code','type','value','min_amount','total_quota','per_user_limit','issued_count','used_count','starts_at','ends_at','is_active'];
    protected $casts = ['value'=>'decimal:2','min_amount'=>'decimal:2','starts_at'=>'datetime','ends_at'=>'datetime','is_active'=>'boolean'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function couponUsers(): HasMany { return $this->hasMany(CouponUser::class); }
}
