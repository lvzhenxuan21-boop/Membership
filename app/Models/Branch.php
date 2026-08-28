<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    protected $fillable = ['tenant_id','name','address','phone','lat','lng','status'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
