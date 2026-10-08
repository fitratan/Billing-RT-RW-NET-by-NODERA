<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;

trait TenantAware
{
    public static function bootTenantAware()
    {
        static::addGlobalScope(new TenantScope);

        // Defense in depth: selalu stamp tenant_id pada row baru saat ada
        // tenant context yang aktif. Superadmin TANPA tenant context (panel
        // apex / CLI / webhook) tidak di-stamp — tapi kalau superadmin berada
        // di dalam konteks tenant (subdomain tenant / impersonasi), row-nya
        // TETAP di-stamp ke tenant itu supaya tidak jadi row "global" yang
        // akhirnya bocor muncul di semua tenant.
        static::creating(function ($model) {
            if (!is_a($model, \Illuminate\Database\Eloquent\Model::class)) {
                return;
            }
            if (!in_array('tenant_id', $model->getFillable())) {
                return;
            }
            // SaaS Platform Subscription Packages (Superadmin) must remain global (tenant_id IS NULL)
            if ($model instanceof \App\Models\Package && ($model->type === 'subscription' || $model->getAttribute('type') === 'subscription')) {
                return;
            }
            $tenantId = TenantScope::currentTenantId();
            if ($tenantId && empty($model->tenant_id)) {
                $model->tenant_id = $tenantId;
            }
        });
    }
}
