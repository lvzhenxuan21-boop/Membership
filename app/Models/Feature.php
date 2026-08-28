<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $fillable = ['tenant_id','name','slug','code','type','unit','description'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function plans(): BelongsToMany { return $this->belongsToMany(MembershipPlan::class,'feature_plan')->withPivot(['value','quota'])->withTimestamps(); }
}
