<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ③ 积分抵现：订单记录使用的积分与抵扣金额
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('points_used')->default(0)->after('pay_amount');
            $table->decimal('points_amount', 10, 2)->default(0)->after('points_used');
        });

        // ④ 保级/降级：成长值周期基线（周期内成长 = growth - growth_base）
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->timestamp('period_started_at')->nullable()->after('growth');
            $table->bigInteger('growth_base')->default(0)->after('period_started_at');
        });

        // ⑤ 签到防重：按自然日唯一（并发下防重复签到）
        Schema::table('check_ins', function (Blueprint $table) {
            $table->date('checked_on')->nullable()->after('checked_in_at');
        });
        DB::table('check_ins')->whereNull('checked_on')->update(['checked_on' => DB::raw('DATE(checked_in_at)')]);
        Schema::table('check_ins', function (Blueprint $table) {
            $table->unique(['tenant_id', 'user_id', 'checked_on']);
        });
    }

    public function down(): void
    {
        Schema::table('check_ins', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'user_id', 'checked_on']);
            $table->dropColumn('checked_on');
        });
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->dropColumn(['period_started_at', 'growth_base']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['points_used', 'points_amount']);
        });
    }
};
