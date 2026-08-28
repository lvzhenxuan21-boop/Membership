<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = ['tenant_id','user_id','balance','frozen','total_recharged','total_consumed'];
    protected $casts = ['balance'=>'decimal:2','frozen'=>'decimal:2','total_recharged'=>'decimal:2','total_consumed'=>'decimal:2'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function transactions(): HasMany { return $this->hasMany(WalletTransaction::class); }
}
