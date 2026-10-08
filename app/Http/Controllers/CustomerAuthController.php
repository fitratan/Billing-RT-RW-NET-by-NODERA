<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class CustomerAuthController extends Controller
{
    public function showLoginForm()
    {
        $rememberId = request()->cookie('nodera_remember_customer');
        if ($rememberId) {
            $customer = Customer::withoutGlobalScopes()->find($rememberId);
            if ($customer) {
                session([
                    'customer_id' => $customer->id,
                    'customer_phone' => $customer->phone,
                    'customer_name' => $customer->name,
                    'customer_tenant_id' => $customer->tenant_id,
                    'logged_in' => true,
                    'tenant_id' => $customer->tenant_id,
                ]);
                return redirect()->to('/portal');
            }
        }

        return Inertia::render('Portal/Login', [
            'tenantName' => session('tenant_name', 'NODERA'),
        ]);
    }

    public function authenticate(Request $request)
    {
        $request->validate([
            'phone' => 'required|string', // Menerima ID Pelanggan, Kode, PPPoE Username, atau No HP
            'pin' => 'required|string|digits:6',
        ], [
            'phone.required' => 'ID Pelanggan, Username PPPoE, atau Nomor HP wajib diisi.',
            'phone.string' => 'Format ID Pelanggan atau nomor HP tidak valid.',
            'pin.required' => 'PIN wajib diisi.',
            'pin.digits' => 'PIN harus 6 digit angka.',
        ]);

        $loginInput = is_string($request->phone) ? trim($request->phone) : '';
        $pin = is_string($request->pin) ? trim($request->pin) : '';

        $cleanPhone = preg_replace('/[^0-9]/', '', $loginInput);
        $phoneVariants = array_filter(array_unique([
            $loginInput,
            $cleanPhone,
            preg_replace('/^08/', '628', $cleanPhone),
            preg_replace('/^628/', '08', $cleanPhone),
            preg_replace('/^\+62/', '0', $loginInput),
            preg_replace('/^\+62/', '62', $loginInput),
        ]));

        $currentTenantId = request()->attributes->get('tenant_id') ?? session('tenant_id');

        // Cari customer berdasarkan berbagai kemungkinan identifier:
        // 1. ID Numerik (id)
        // 2. Kode Pelanggan (code)
        // 3. Username PPPoE (pppoe_username)
        // 4. No Telepon / WhatsApp (phone)
        // 5. Static IP (static_ip)
        $findCustomer = function ($scopedTenantId = null) use ($loginInput, $cleanPhone, $phoneVariants) {
            $query = Customer::withoutGlobalScopes();
            if ($scopedTenantId) {
                $query->where('tenant_id', $scopedTenantId);
            }

            return $query->where(function ($q) use ($loginInput, $cleanPhone, $phoneVariants) {
                if (is_numeric($loginInput)) {
                    $q->where('id', (int) $loginInput)
                      ->orWhere('code', $loginInput);
                } else {
                    $q->where('code', $loginInput);
                }

                $q->orWhere('pppoe_username', $loginInput)
                  ->orWhere('ip_address', $loginInput);

                if (!empty($phoneVariants)) {
                    $q->orWhereIn('phone', $phoneVariants);
                }
                if (!empty($cleanPhone)) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', '') = ?", [$cleanPhone]);
                }
            })->first();
        };

        // Prioritaskan tenant domain aktif jika ada, jika tidak ketemu cari lintas tenant
        $customer = null;
        if ($currentTenantId) {
            $customer = $findCustomer($currentTenantId);
        }
        if (!$customer) {
            $customer = $findCustomer(null);
        }

        if (!$customer) {
            return back()->with('error', 'ID Pelanggan, Username PPPoE, atau Nomor HP tidak terdaftar')->withInput();
        }

        $tenant = $customer->tenant_id ? Tenant::withoutGlobalScopes()->find($customer->tenant_id) : null;
        if ($tenant && (!$tenant->is_active || $tenant->isExpired())) {
            return back()->with('error', 'Akun tidak dapat diakses saat ini. Hubungi administrator.');
        }

        $dbPin = $customer->portal_password ?? '';
        $isPinValid = false;

        // Logika verifikasi PIN:
        // 1. PIN default '123456' selalu valid sebagai default bawaan sistem
        // 2. PIN kustom yang telah diubah pelanggan diverifikasi via Hash::check / plaintext
        // 3. Fallback password PPPoE jika pelanggan menggunakan PIN sesuai secret mikrotik
        if ($pin === '123456') {
            $isPinValid = true;
        } elseif (!empty($dbPin) && (Hash::check($pin, $dbPin) || $pin === $dbPin)) {
            $isPinValid = true;
        } elseif (!empty($customer->pppoe_password) && ($pin === $customer->pppoe_password || Hash::check($pin, $customer->pppoe_password))) {
            $isPinValid = true;
        }

        if (!$isPinValid) {
            return back()->with('error', 'PIN salah. Masukkan PIN yang benar atau gunakan default 123456.')->withInput();
        }

        session([
            'customer_id' => $customer->id,
            'customer_phone' => $customer->phone,
            'customer_name' => $customer->name,
            'customer_tenant_id' => $customer->tenant_id,
            'logged_in' => true,
        ]);

        if ($customer->tenant_id) {
            session([
                'tenant_id' => $customer->tenant_id,
            ]);
        }

        if ($request->boolean('remember', true)) {
            cookie()->queue('nodera_remember_customer', $customer->id, 525600);
        }

        return redirect()->to('/portal');
    }

    public function logout(Request $request)
    {
        session()->forget([
            'customer_id', 'customer_phone', 'customer_name',
            'customer_tenant_id', 'logged_in',
        ]);
        cookie()->queue(cookie()->forget('nodera_remember_customer'));

        return redirect('/portal/login')->with('msg', 'Berhasil logout');
    }
}
