<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = ['wallet_id','type','amount','balance_after','order_no','description','operator','meta'];
    protected $casts = ['amount'=>'decimal:2','balance_after'=>'decimal:2','meta'=>'array'];
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
}
