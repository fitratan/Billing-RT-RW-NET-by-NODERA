@extends('layouts.admin')
@section('title', 'Pengaturan Sidebar')
@section('content')
<div class="container-fluid px-0">
    <h1 class="fs-4 fw-bold mb-4"><i class="bi bi-list text-primary me-2"></i>Pengaturan Sidebar</h1>
    <form method="POST" action="/admin/sidebar-settings/save">
        @csrf
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body">
                <p class="small text-muted mb-3">Centang menu yang ingin ditampilkan di sidebar</p>
                @php
                    $allMenus = [
                        'dashboard' => 'Dashboard', 'analytics' => 'Analytics',
                        'billing' => 'Billing', 'customers' => 'Pelanggan',
                        'packages' => 'Paket', 'invoices' => 'Invoice',
                        'mikrotik' => 'MikroTik', 'pppoe' => 'PPPoE',
                        'hotspot' => 'Hotspot', 'olt' => 'OLT',
                        'genieacs' => 'GenieACS', 'map' => 'Peta ONU',
                        'trouble' => 'Gangguan', 'employees' => 'Karyawan',
                        'inventory' => 'Inventory', 'attendance' => 'Absensi',
                        'payroll' => 'Payroll', 'finance' => 'Keuangan',
                        'broadcast' => 'Broadcast', 'voucher' => 'Voucher',
                    ];
                @endphp
                <div class="row g-3">
                    @foreach($allMenus as $key => $label)
                    @php $checked = !isset($settings[$key]) || $settings[$key]; @endphp
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="form-check">
                            <input type="checkbox" name="menu[]" value="{{ $key }}" class="form-check-input" id="menu_{{ $key }}" {{ $checked ? 'checked' : '' }}>
                            <label class="form-check-label small" for="menu_{{ $key }}">{{ $label }}</label>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 rounded-3 fw-semibold py-2 mt-3"><i class="bi bi-floppy me-1"></i> Simpan</button>
    </form>
</div>
@endsection
