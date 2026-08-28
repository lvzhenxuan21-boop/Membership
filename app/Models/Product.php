<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = ['tenant_id','shop_id','name','slug','description','cover','price','original_price','stock','sales','status','specs'];
    protected $casts = ['specs'=>'array','price'=>'decimal:2'];

    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
