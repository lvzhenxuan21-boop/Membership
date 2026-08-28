<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointLedger extends Model
{
    protected $fillable = ['tenant_id','user_id','type','points','balance_after','source_type','source_id','description','operator','expired_at'];
    protected $casts = ['expired_at'=>'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
