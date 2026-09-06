<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = ['tenant_id','shop_id','name','slug','description','cover','price','original_price','stock','sales','status','specs'];
    protected $casts = ['specs'=>'array','price'=>'decimal:2'];

    // 封面兼容两种存储：历史完整 URL / FileUpload 的 public disk 相对路径
    public function getCoverUrlAttribute(): ?string
    {
        if (!$this->cover) return null;
        return Str::startsWith($this->cover, ['http://', 'https://', '/'])
            ? $this->cover
            : Storage::disk('public')->url($this->cover);
    }

    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
