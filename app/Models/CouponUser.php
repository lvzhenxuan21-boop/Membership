<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUser extends Model
{
    protected $table = 'coupon_user';
    protected $fillable = ['coupon_id','user_id','tenant_id','code','status','used_at','expired_at'];
    protected $casts = ['used_at'=>'datetime','expired_at'=>'datetime'];
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
