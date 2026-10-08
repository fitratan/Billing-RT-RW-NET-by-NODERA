<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DesktopLicense;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DesktopLicenseApiController extends Controller
{
    /**
     * Aktivasi masa percobaan 7 hari gratis (Free 7-Day Trial).
     * Terikat pada HWID unik (1 perangkat hanya bisa 1 kali trial).
     */
    public function activateTrial(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hwid'        => 'required|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'os_info'     => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Format request tidak valid. Hardware ID (HWID) wajib disertakan.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $hwid = trim($request->input('hwid'));

        // Cek apakah HWID ini sudah pernah mengklaim trial sebelumnya
        $existingTrial = DesktopLicense::where('hwid', $hwid)
            ->where(function ($q) {
                $q->where('product_name', 'LIKE', '%Trial%')
                  ->orWhere('notes', 'LIKE', '%TRIAL%');
            })
            ->first();

        if ($existingTrial) {
            return response()->json([
                'success' => false,
                'code'    => 'TRIAL_ALREADY_USED',
                'message' => 'Perangkat ini (' . ($existingTrial->device_name ?: 'HWID Terdaftar') . ') sudah pernah menggunakan masa percobaan 7 hari gratis. Silakan beli lisensi resmi untuk melanjutkan penggunaan.',
                'license_key' => $existingTrial->license_key,
                'expires_at'  => $existingTrial->expires_at?->toIso8601String(),
            ], 403);
        }

        // Buat Lisensi Trial 7 Hari Baru
        $trialKey = DesktopLicense::generateKey('NDR-TRL');
        $expiresAt = Carbon::now()->addDays(7);

        $license = DesktopLicense::create([
            'tenant_id'        => null,
            'vpn_user_id'      => null,
            'user_id'          => null,
            'license_key'      => $trialKey,
            'product_name'     => 'Mikhmon Desktop (Trial 7 Hari)',
            'hwid'             => $hwid,
            'device_name'      => $request->input('device_name') ?: 'Windows PC (Trial)',
            'os_info'          => $request->input('os_info') ?: 'Windows 64-bit',
            'ip_address'       => $request->ip(),
            'status'           => 'ACTIVE',
            'activation_count' => 1,
            'max_activations'  => 1,
            'activated_at'     => Carbon::now(),
            'expires_at'       => $expiresAt,
            'last_heartbeat_at'=> Carbon::now(),
            'features'         => [
                'multi_router'  => true,
                'warung_pos'    => true,
                'whatsapp'      => true,
                'thermal_print' => true,
                'telegram'      => true,
                'noderapay'     => true,
            ],
            'notes'            => 'TRIAL_7_DAYS_AUTOGEN',
        ]);

        $expString = $license->expires_at->toIso8601String();
        $signature = hash('sha256', $license->license_key . '|' . $license->hwid . '|' . $expString . '|NODERA_OFFLINE_SECRET_2026');

        return response()->json([
            'success' => true,
            'code'    => 'TRIAL_ACTIVATED',
            'message' => 'Selamat! Masa percobaan 7 hari gratis berhasil diaktifkan.',
            'data'    => [
                'license_key'  => $license->license_key,
                'product_name' => $license->product_name,
                'status'       => $license->status,
                'hwid'         => $license->hwid,
                'signature'    => $signature,
                'device_name'  => $license->device_name,
                'activated_at' => $license->activated_at->toIso8601String(),
                'expires_at'   => $license->expires_at->toIso8601String(),
                'days_left'    => 7,
                'is_trial'     => true,
                'features'     => $license->features,
                'brand'        => 'NODERA Digital Network',
                'support_url'  => 'https://dgtlnetsolution.com',
            ],
        ]);
    }

    /**
     * Aktivasi lisensi desktop pertama kali / bind HWID.
     */
    public function activate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required|string|max:64',
            'hwid'        => 'required|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'os_info'     => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Format request tidak valid.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $key = trim($request->input('license_key'));
        $hwid = trim($request->input('hwid'));

        $license = DesktopLicense::where('license_key', $key)->first();

        if (!$license) {
            return response()->json([
                'success' => false,
                'code'    => 'INVALID_KEY',
                'message' => 'License Key tidak ditemukan. Pastikan Anda membeli lisensi resmi dari NODERA.',
            ], 404);
        }

        if ($license->status === 'REVOKED' || $license->status === 'SUSPENDED') {
            return response()->json([
                'success' => false,
                'code'    => 'LICENSE_SUSPENDED',
                'message' => 'Lisensi ini telah dinonaktifkan atau ditangguhkan oleh Superadmin.',
            ], 403);
        }

        if ($license->isExpired()) {
            return response()->json([
                'success' => false,
                'code'    => 'LICENSE_EXPIRED',
                'message' => 'Masa aktif lisensi telah berakhir pada ' . $license->expires_at?->format('d M Y H:i') . '. Silakan lakukan perpanjangan di Cloud Panel.',
                'expires_at' => $license->expires_at?->toIso8601String(),
            ], 403);
        }

        // Cek binding HWID
        if (!empty($license->hwid) && $license->hwid !== $hwid) {
            return response()->json([
                'success' => false,
                'code'    => 'HWID_MISMATCH',
                'message' => 'Lisensi ini sudah terikat pada perangkat lain (' . ($license->device_name ?: 'Perangkat Terdaftar') . '). Hubungi admin jika ingin memindahkan lisensi.',
            ], 403);
        }

        // Bind HWID & update status
        $license->hwid = $hwid;
        $license->device_name = $request->input('device_name') ?: $license->device_name;
        $license->os_info = $request->input('os_info') ?: $license->os_info;
        $license->ip_address = $request->ip();
        $license->status = 'ACTIVE';
        if (!$license->activated_at) {
            $license->activated_at = Carbon::now();
        }
        $license->last_heartbeat_at = Carbon::now();
        $license->activation_count = ($license->activation_count ?: 0) + 1;
        $license->save();

        $expString = $license->expires_at ? $license->expires_at->toIso8601String() : 'LIFETIME';
        $signature = hash('sha256', $license->license_key . '|' . $license->hwid . '|' . $expString . '|NODERA_OFFLINE_SECRET_2026');

        return response()->json([
            'success' => true,
            'code'    => 'ACTIVATED',
            'message' => 'Lisensi berhasil diaktivasi.',
            'data'    => [
                'license_key'  => $license->license_key,
                'product_name' => $license->product_name,
                'status'       => $license->status,
                'hwid'         => $license->hwid,
                'signature'    => $signature,
                'device_name'  => $license->device_name,
                'activated_at' => $license->activated_at?->toIso8601String(),
                'expires_at'   => $license->expires_at?->toIso8601String(),
                'days_left'    => $license->expires_at ? max(0, Carbon::now()->diffInDays($license->expires_at, false)) : 9999,
                'features'     => $license->features ?: [
                    'multi_router' => true,
                    'warung_pos'   => true,
                    'whatsapp'     => true,
                    'thermal_print'=> true,
                ],
                'brand'        => 'NODERA Digital Network',
                'support_url'  => 'https://dgtlnetsolution.com',
            ],
        ]);
    }

    /**
     * Verifikasi status lisensi / Heartbeat check.
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required|string|max:64',
            'hwid'        => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tidak lengkap.',
            ], 422);
        }

        $key = trim($request->input('license_key'));
        $hwid = trim($request->input('hwid'));

        $license = DesktopLicense::where('license_key', $key)->first();

        if (!$license) {
            return response()->json([
                'success' => false,
                'code'    => 'INVALID_KEY',
                'message' => 'License Key tidak valid.',
            ], 404);
        }

        if (empty($license->hwid)) {
            return response()->json([
                'success' => false,
                'code'    => 'HWID_UNBOUND',
                'message' => 'Lisensi ini telah direset dari Cloud Panel. Silakan lakukan aktivasi ulang pada perangkat yang ingin digunakan.',
            ], 403);
        }

        if ($license->hwid !== $hwid) {
            return response()->json([
                'success' => false,
                'code'    => 'HWID_MISMATCH',
                'message' => 'Hardware ID tidak cocok dengan lisensi terdaftar (Lisensi telah dipindahkan/diikat ke perangkat lain).',
            ], 403);
        }

        if ($license->status !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'code'    => 'NOT_ACTIVE',
                'status'  => $license->status,
                'message' => 'Status lisensi saat ini: ' . $license->status,
            ], 403);
        }

        if ($license->isExpired()) {
            $license->status = 'EXPIRED';
            $license->save();

            return response()->json([
                'success'    => false,
                'code'       => 'LICENSE_EXPIRED',
                'message'    => 'Masa aktif lisensi telah habis.',
                'expires_at' => $license->expires_at?->toIso8601String(),
            ], 403);
        }

        // Update heartbeat
        $license->last_heartbeat_at = Carbon::now();
        $license->ip_address = $request->ip();
        $license->save();

        $expString = $license->expires_at ? $license->expires_at->toIso8601String() : 'LIFETIME';
        $signature = hash('sha256', $license->license_key . '|' . $license->hwid . '|' . $expString . '|NODERA_OFFLINE_SECRET_2026');

        return response()->json([
            'success' => true,
            'code'    => 'VALID',
            'message' => 'Lisensi aktif dan valid.',
            'data'    => [
                'license_key'  => $license->license_key,
                'product_name' => $license->product_name,
                'status'       => 'ACTIVE',
                'hwid'         => $license->hwid,
                'signature'    => $signature,
                'expires_at'   => $license->expires_at?->toIso8601String(),
                'days_left'    => $license->expires_at ? max(0, Carbon::now()->diffInDays($license->expires_at, false)) : 9999,
                'features'     => $license->features ?: [
                    'multi_router' => true,
                    'warung_pos'   => true,
                    'whatsapp'     => true,
                    'thermal_print'=> true,
                ],
            ],
        ]);
    }
}
