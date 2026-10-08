<?php

namespace App\Actions\User;

use App\Models\User;

class ResetUserPassword
{
    public function execute(User $user, ?string $password = null): string
    {
        $password = $password ?? substr(md5(uniqid()), 0, 8);
        $user->password = bcrypt($password);
        $user->save();

        if ($user->tenant_id || $user->email || $user->phone) {
            try {
                $tenant = $user->tenant_id ? \App\Models\Tenant::withoutGlobalScopes()->find($user->tenant_id) : null;
                $vpnUserId = $tenant?->vpn_user_id;
                $vpnUser = $vpnUserId ? \App\Models\VpnUser::find($vpnUserId) : null;
                if (!$vpnUser && ($user->email || $user->phone)) {
                    $vpnUser = \App\Models\VpnUser::where('email', $user->email)->orWhere('phone', $user->phone)->first();
                }
                if ($vpnUser) {
                    $vpnUser->update(['password' => bcrypt($password)]);
                }
            } catch (\Throwable $e) {}
        }

        return $password;
    }
}
