<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['tenant_id','shop_id','user_id','order_no','status','total_amount','discount_amount','pay_amount','platform_fee','payment_channel','payment_order_no','address','paid_at'];
    protected $casts = ['address'=>'array','paid_at'=>'datetime'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
}
