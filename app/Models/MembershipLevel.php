<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipLevel extends Model
{
    protected $fillable = ['tenant_id','name','slug','level','min_points','min_growth','discount_rate','points_multiplier','benefits','badge_color','is_default'];
    protected $casts = ['benefits'=>'array','discount_rate'=>'decimal:2','is_default'=>'boolean'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function members(): HasMany { return $this->hasMany(MemberProfile::class, 'membership_level_id'); }
}
