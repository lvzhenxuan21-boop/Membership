<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 租户状态增加 pending（待审核）：自助开店在 MEMBERSHIP_TENANT_AUTO_ACTIVATE=false 时
     * 新租户置为 pending，店铺解析只认 active，平台在后台审核激活。
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['active', 'pending', 'suspended', 'trial'])->default('active')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['active', 'suspended', 'trial'])->default('active')->change();
        });
    }
};
