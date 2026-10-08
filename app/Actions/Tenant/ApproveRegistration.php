<?php

namespace App\Actions\Tenant;

use App\Events\RegistrationApproved;
use App\Models\Package;
use App\Models\RegistrationRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveRegistration
{
    public function execute(RegistrationRequest $req): array
    {
        return DB::transaction(function () use ($req) {
            $package = $req->package ?: ($req->package_id ? Package::find($req->package_id) : null);
            if (!$package) {
                $package = Package::whereNull('tenant_id')
                    ->where('type', 'subscription')
                    ->where('is_active', true)
                    ->orderBy('monthly_price', 'asc')
                    ->first();
            }

            $defaultMaxCust = (int) (\App\Models\Setting::getValue('default_max_customers') ?: 500);
            $maxCust = $package ? (int) $package->max_customers : $defaultMaxCust;
            // Trial 1 bulan untuk pendaftaran baru / paket Basic
            $isBasicTrial = $package && $package->isBasicSubscription();
            $duration = (int) ($req->duration ?? 1);
            if ($duration <= 0) {
                $duration = 1;
            }

            $maxRouters = (int) ($package->max_routers ?? 5);
            $packageName = $package?->name ?? ($maxCust > 0 ? "Paket {$maxCust} Pelanggan" : "Paket Unlimited");

            $settings = [
                'package_id' => $package?->id,
                'package_name' => $packageName,
                'max_customers' => $maxCust,
                'max_routers' => $maxRouters,
                'subscribed_price' => (int) ($package->monthly_price ?? $package->price ?? 0),
                'subscribed_duration' => $duration,
                'is_trial' => $isBasicTrial,
                'trial_duration' => $isBasicTrial ? $duration : 0,
            ];

            $trialEndsAt = $isBasicTrial ? now()->addMonths($duration) : null;

            // Password = yang diinput registran di form register
            // (password_hash sudah bcrypt). JANGAN generate random —
            // registran harus bisa login dengan password yang dia daftarkan.
            $registeredPassword = !empty($req->password_hash);
            $passwordHash = $registeredPassword
                ? $req->password_hash
                : bcrypt(\Illuminate\Support\Str::random(10));
            $adminEmail = !empty($req->email) ? $req->email : 'admin@' . $req->slug . '.local';

            // 1. Provision / Sync with VpnUser (Panel)
            $vpnUser = null;
            if (!empty($req->email) || !empty($req->phone)) {
                $vpnUser = \App\Models\VpnUser::where(function ($q) use ($req) {
                    if (!empty($req->email)) {
                        $q->where('email', $req->email);
                    }
                    if (!empty($req->phone)) {
                        $q->orWhere('phone', $req->phone);
                    }
                })->first();
            }

            if (!$vpnUser) {
                try {
                    $vpnUser = \App\Models\VpnUser::create([
                        'name' => $req->name,
                        'email' => $adminEmail,
                        'phone' => $req->phone,
                        'password' => $passwordHash,
                        'saldo' => 0,
                        'bonus_saldo' => 0,
                        'is_active' => true,
                    ]);
                } catch (\Throwable $e) {
                    $vpnUser = \App\Models\VpnUser::where('email', $adminEmail)->orWhere('phone', $req->phone)->first();
                }
            } else {
                $vpnUser->update([
                    'password' => $passwordHash,
                    'name' => $req->name ?: $vpnUser->name,
                    'phone' => $req->phone ?: $vpnUser->phone,
                ]);
            }

            $vpnUserId = $vpnUser?->id;
            $settings['vpn_user_id'] = $vpnUserId;
            $settings['auto_renew'] = $settings['auto_renew'] ?? true;

            $tenantData = [
                'name' => $req->name,
                'slug' => $req->slug,
                'email' => $req->email,
                'phone' => $req->phone,
                'is_active' => true,
                'max_customers' => $maxCust,
                'max_routers' => $maxRouters,
                'expired_at' => $duration > 0 ? now()->addMonths($duration) : null,
                'settings' => $settings,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('tenants', 'vpn_user_id') && $vpnUserId) {
                $tenantData['vpn_user_id'] = $vpnUserId;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('tenants', 'auto_renew')) {
                $tenantData['auto_renew'] = true;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('tenants', 'trial_ends_at')) {
                $tenantData['trial_ends_at'] = $trialEndsAt;
            }

            $tenant = Tenant::where('slug', $req->slug)->first();
            if (!$tenant) {
                $tenant = Tenant::create($tenantData);
            } else {
                $tenant->update($tenantData);
            }

            $baseUsername = $req->username ?: $req->slug;
            $user = User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where(function ($q) use ($baseUsername, $adminEmail) {
                    $q->where('username', $baseUsername)->orWhere('email', $adminEmail);
                })
                ->first();

            if ($user) {
                $user->update([
                    'name' => $req->name,
                    'password' => $passwordHash,
                    'phone' => $req->phone,
                    'role' => 'admin',
                    'is_active' => true,
                ]);
            } else {
                $uniqueUsername = $baseUsername;
                $suffix = 1;
                while (User::withoutGlobalScopes()->where('username', $uniqueUsername)->exists()) {
                    $uniqueUsername = $baseUsername . '_' . $suffix++;
                }

                $user = User::create([
                    'name'     => $req->name,
                    'username' => $uniqueUsername,
                    'email'    => $adminEmail,
                    'phone'    => $req->phone,
                    'password' => $passwordHash,
                    'role'     => 'admin',
                    'tenant_id'=> $tenant->id,
                    'is_active'=> true,
                ]);
            }

            if ($vpnUser && !empty($vpnUser->google_id)) {
                $user->update([
                    'google_id' => $user->google_id ?: $vpnUser->google_id,
                    'avatar'    => $user->avatar ?: $vpnUser->avatar,
                ]);
            }

            $req->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'notes' => "Tenant created. WA: {$req->phone}"
                    . ($registeredPassword ? ' | Password: sesuai pendaftaran' : '')
                    . ($isBasicTrial ? " | TRIAL {$duration} bulan (aktif s/d " . ($trialEndsAt ? $trialEndsAt->format('d/m/Y') : '-') . ")" : ''),
            ]);

            try {
                RegistrationRequest::retractAndNotifyApproval($req, $tenant, $user);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[ApproveRegistration] Telegram retract and notify failed: ' . $e->getMessage());
            }

            event(new RegistrationApproved($req, $tenant, $user, $registeredPassword ? '' : $passwordHash));

            return [
                'tenant' => $tenant,
                'user' => $user,
                'password' => $registeredPassword ? '(sesuai pendaftaran)' : $passwordHash,
            ];
        });
    }
}
