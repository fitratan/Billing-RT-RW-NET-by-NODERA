<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Context;

class TenantScope implements Scope
{
    protected static bool $resolving = false;

    /**
     * Ambil tenant aktif untuk request ini.
     * Prioritas:
     * 0. Standalone Mode -> selalu null (single-tenant local deployment)
     * 1. Subdomain tenant / impersonasi eksplisit pada request attribute
     * 2. Rute superadmin/webhook -> unscoped (return null)
     * 3. Context (CLI / async worker)
     * 4. Session tenant_id
     * 5. Authenticated user tenant_id (Auth::user() / session admin_id / collector_id / technician_id)
     */
    public static function currentTenantId(): ?int
    {
        // Standalone Mode (Single Tenant ISP) -> no multi-tenant scoping needed
        if (filter_var(env('STANDALONE_MODE', false), FILTER_VALIDATE_BOOLEAN) || config('app.standalone_mode', false)) {
            return null;
        }

        // Prevent infinite recursion loops during user / model hydration
        if (static::$resolving) {
            return session('tenant_id') ? (int) session('tenant_id') : null;
        }

        static::$resolving = true;

        try {
            if (app()->bound('request')) {
                $request = app('request');
                if ($request) {
                    // 1. Authoritative subdomain / impersonation set on request attributes
                    if ($request->attributes->has('tenant_id')) {
                        $attrTenant = $request->attributes->get('tenant_id');
                        if ($attrTenant !== null && $attrTenant !== '') {
                            return (int) $attrTenant;
                        }
                        if (session('admin_role') === 'superadmin' && !session('impersonating')) {
                            $path = $request->path();
                            if (!str_starts_with($path, 'admin') && !str_starts_with($path, 'billing')) {
                                return null;
                            }
                        }
                    }

                    // 2. SuperAdmin and Webhook routes MUST be unscoped
                    $path = $request->path();
                    if (str_starts_with($path, 'superadmin') || str_starts_with($path, 'webhook') || str_starts_with($path, 'nodera/superadmin')) {
                        return null;
                    }
                }
            }

            // 3. Context (Queue, CLI worker)
            if (Context::has('tenant_id')) {
                $ctx = Context::get('tenant_id');
                if ($ctx !== null && $ctx !== '') {
                    return (int) $ctx;
                }
            }

            // 4. Session tenant_id
            $sessionTenant = session('tenant_id');
            if ($sessionTenant) {
                $tenantExists = \Illuminate\Support\Facades\DB::table('tenants')->where('id', (int) $sessionTenant)->exists();
                if ($tenantExists) {
                    return (int) $sessionTenant;
                }
                session()->forget('tenant_id');
            }

            // 5. Authenticated User (Laravel Auth)
            if (auth()->guard()->hasUser()) {
                $authUser = auth()->user();
                if ($authUser && $authUser->tenant_id) {
                    session(['tenant_id' => (int) $authUser->tenant_id]);
                    return (int) $authUser->tenant_id;
                }
            }

            // 6. Admin Session ID
            if (session('admin_id')) {
                $user = \App\Models\User::withoutGlobalScopes()->find(session('admin_id'));
                if ($user && $user->tenant_id) {
                    session(['tenant_id' => (int) $user->tenant_id]);
                    return (int) $user->tenant_id;
                }
            }

            // 7. Collector Session
            if (session('collector_id')) {
                $collector = \App\Models\Collector::withoutGlobalScopes()->find(session('collector_id'));
                if ($collector && $collector->tenant_id) {
                    session(['tenant_id' => (int) $collector->tenant_id]);
                    return (int) $collector->tenant_id;
                }
            }

            // 8. Technician Session
            if (session('technician_id')) {
                $tech = \App\Models\User::withoutGlobalScopes()->find(session('technician_id'));
                if ($tech && $tech->tenant_id) {
                    session(['tenant_id' => (int) $tech->tenant_id]);
                    return (int) $tech->tenant_id;
                }
            }

            // 9. Customer Session
            if (session('customer_tenant_id')) {
                $custTenant = (int) session('customer_tenant_id');
                session(['tenant_id' => $custTenant]);
                return $custTenant;
            }
            if (session('customer_id')) {
                $cust = \App\Models\Customer::withoutGlobalScopes()->find(session('customer_id'));
                if ($cust && $cust->tenant_id) {
                    session(['tenant_id' => (int) $cust->tenant_id]);
                    return (int) $cust->tenant_id;
                }
            }

            return null;
        } finally {
            static::$resolving = false;
        }
    }


    /**
     * Tenant id untuk raw DB::table(...) queries yang tidak kena global scope Eloquent.
     */
    public static function rawTenantId(): ?int
    {
        return static::currentTenantId();
    }

    public function apply(Builder $builder, Model $model)
    {
        $tenantId = static::currentTenantId();

        if ($tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }
}
