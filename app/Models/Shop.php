<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $fillable = ['tenant_id','branch_id','name','slug','logo','contact_phone','description','status','platform_fee_rate','settings'];
    protected $casts = ['settings'=>'array','platform_fee_rate'=>'decimal:4'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function products(): HasMany { return $this->hasMany(Product::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
}
