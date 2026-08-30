<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    protected $fillable = ['tenant_id','branch_id','user_id','checked_in_at','checked_on','method'];
    protected $casts = ['checked_in_at'=>'datetime','checked_on'=>'date:Y-m-d'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
}
