<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class TechnicianController extends Controller
{
    /**
     * List all technicians (Admin view)
     */
    private function tenantId()
    {
        return session('tenant_id') ?: auth()->user()?->tenant_id;
    }

    public function list()
    {
        if (session('admin_role') !== 'admin') {
            return redirect()->to('/admin')->with('error', 'Hanya admin yang bisa akses menu ini.');
        }

        $technicians = User::where('role', 'technician')
            ->where('tenant_id', $this->tenantId())
            ->get();
        return view('admin.technician.list', compact('technicians'));
    }

    /**
     * Add new technician
     */
    public function add(Request $request)
    {
        if (session('admin_role') !== 'admin') {
            abort(403);
        }

        if (!\App\Services\LicenseService::canAddStaff()) {
            return redirect()->back()->withInput()->with('error', 'Batas kuota Community Edition tercapai (Maksimal ' . \App\Services\LicenseService::MAX_FREE_STAFF . ' Staff). Silakan aktivasi lisensi Pro.');
        }

        $request->validate([
            'username' => 'required|string|max:50|unique:users,username',
            'password' => 'required|string|min:6',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        User::create([
            'username' => $request->username,
            'password' => bcrypt($request->password),
            'name' => $request->name,
            'phone' => $request->phone,
            'role' => 'technician',
            'is_active' => true,
            'tenant_id' => $this->tenantId(),
        ]);

        return redirect()->to('/admin/technicians')->with('msg', 'Teknisi berhasil ditambahkan');
    }

    /**
     * Update technician data
     */
    public function update(Request $request, $id)
    {
        if (session('admin_role') !== 'admin') {
            abort(403);
        }

        $request->validate([
            'username' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
        ]);

        $data = [
            'username' => $request->username,
            'name' => $request->name,
            'phone' => $request->phone,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('password')) {
            $tech = User::find($id);
            if (session('tenant_slug') === 'demo' || in_array($tech->username ?? '', ['teknisi_demo', 'demo', 'kolektor_demo'])) {
                return redirect()->to('/admin/technicians')->with('error', 'Pengubahan password akun demo dinonaktifkan.');
            }
            $data['password'] = bcrypt($request->password);
        }

        User::where('id', $id)->where('role', 'technician')->where('tenant_id', $this->tenantId())->update($data);
        return redirect()->to('/admin/technicians')->with('msg', 'Data teknisi berhasil diperbarui');
    }

    /**
     * Delete technician
     */
    public function delete($id)
    {
        if (session('admin_role') !== 'admin') {
            abort(403);
        }

        User::where('id', $id)->where('role', 'technician')->where('tenant_id', $this->tenantId())->delete();
        return redirect()->to('/admin/technicians')->with('msg', 'Teknisi berhasil dihapus');
    }
}
