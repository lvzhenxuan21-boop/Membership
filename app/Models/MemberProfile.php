<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberProfile extends Model
{
    protected $fillable = ['user_id','tenant_id','branch_id','membership_level_id','member_no','real_name','phone','id_card','birthday','gender','avatar','points','growth','period_started_at','growth_base','balance','total_orders','total_spent','joined_at','last_active_at','status','extra'];
    protected $casts = ['birthday'=>'date','joined_at'=>'datetime','last_active_at'=>'datetime','period_started_at'=>'datetime','extra'=>'array','points'=>'integer','growth'=>'integer','balance'=>'decimal:2','total_spent'=>'decimal:2'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function level(): BelongsTo { return $this->belongsTo(MembershipLevel::class, 'membership_level_id'); }

    protected static function booted(): void
    {
        static::creating(function ($m) {
            if (empty($m->member_no)) {
                $m->member_no = 'M'.date('Ymd').str_pad((string)(MemberProfile::whereDate('created_at', today())->count()+1), 6, '0', STR_PAD_LEFT);
            }
            if (empty($m->joined_at)) $m->joined_at = now();
            if (empty($m->status)) $m->status = 'active';
        });
    }

    public function recalcLevel(): void
    {
        $level = MembershipLevel::where('tenant_id', $this->tenant_id)
            ->where('min_growth', '<=', $this->growth)
            ->orderByDesc('level')
            ->first();
        if ($level && $level->id !== $this->membership_level_id) {
            $this->update(['membership_level_id'=>$level->id]);
        }
    }
}
