<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'tenant_id','user_id','order_no','transaction_no','business_type','business_id',
        'subject','amount','original_amount','discount_amount','coupon_id','currency',
        'channel','status','pay_url','channel_data','callback_data','meta',
        'paid_at','expired_at','refunded_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'channel_data' => 'array',
        'callback_data' => 'array',
        'meta' => 'array',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isPaid(): bool { return $this->status === 'paid'; }
    public function isExpired(): bool
    {
        return $this->expired_at && $this->expired_at->isPast() && $this->isPending();
    }

    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function scopePaid($q) { return $q->where('status', 'paid'); }
}
