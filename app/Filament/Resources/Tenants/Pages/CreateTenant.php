<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use App\Services\TenantProvisioning;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    // 后台建租户统一走 TenantProvisioning：自动带总店 + 默认等级，
    // 避免手建"裸租户"（无总店/默认等级，会员注册拿不到等级与折扣）
    protected function handleRecordCreation(array $data): Model
    {
        $hasAdmin = filled($data['admin_email'] ?? null);
        $password = $data['admin_password'] ?? null;
        if ($hasAdmin && blank($password)) {
            $password = Str::random(12) . 'Aa1'; // 未填密码时生成强随机初始密码
        }

        [$tenant, $admin] = app(TenantProvisioning::class)->provision(
            $data['name'],
            $data['slug'],
            $data['contact_name'] ?? null,
            null,
            $hasAdmin ? ['name' => $data['admin_name'] ?? $data['admin_email'], 'email' => $data['admin_email'], 'password' => $password] : null,
        );

        // 表单里租户级的额外字段以表单为准（status/contact_phone/settings）
        $overrides = collect($data)->only(['status', 'contact_phone', 'settings'])->filter(fn ($v) => filled($v))->all();
        if ($overrides !== []) {
            $tenant->update($overrides);
        }

        return $tenant;
    }
}
