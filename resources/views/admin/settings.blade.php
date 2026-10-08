@extends('layouts.admin')

@section('title', 'Menu')

@section('content')
<div class="container-fluid px-0">
    <h1 class="fs-4 fw-bold mb-4" style="color:var(--text);">Pengaturan</h1>

    @if(session('msg'))
    <div class="alert alert-success alert-dismissible fade show py-2 small">@csrf
        <i class="bi bi-check-circle me-1"></i> {{ session('msg') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:10px;"></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show py-2 small">
        <i class="bi bi-exclamation-circle me-1"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:10px;"></button>
    </div>
    @endif

    {{-- Info Akun --}}
    @if($tenant)
    <div class="card shadow-sm rounded-3 border-0 mb-4">
        <div class="card-body">
            <h6 class="fw-semibold mb-3 d-flex align-items-center gap-2" style="color:var(--text);"><i class="bi bi-info-circle text-primary"></i> Info Akun</h6>
            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3">
                        <p class="small text-muted mb-0">Tenant</p>
                        <p class="fw-semibold mt-1 mb-0" style="color:var(--text);">{{ $tenant->name }}</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3">
                        <p class="small text-muted mb-0">Paket</p>
                        <p class="fw-semibold mt-1 mb-0" style="color:var(--text);">{{ $packageName }}</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3">
                        <p class="small text-muted mb-0">Expired</p>
                        <p class="fw-semibold {{ $tenant->expired_at && now()->gt($tenant->expired_at) ? 'text-danger' : '' }} mt-1 mb-0" style="color:var(--text);">
                            {{ $tenant->expired_at ? \Carbon\Carbon::parse($tenant->expired_at)->format('d M Y') : '-' }}
                        </p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3">
                        <p class="small text-muted mb-0">Status</p>
                        <p class="fw-semibold {{ $tenant->isExpired() ? 'text-danger' : 'text-success' }} mt-1 mb-0">
                            {{ $tenant->isExpired() ? 'Expired' : ($tenant->is_active ? 'Aktif' : 'Nonaktif') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <form method="POST" action="/admin/my-settings/save" enctype="multipart/form-data">
        @csrf
        <div class="card shadow-sm rounded-3 border-0 mb-4">
            <div class="card-body">
                <h6 class="fw-semibold mb-3 d-flex align-items-center gap-2" style="color:var(--text);"><i class="bi bi-building text-primary"></i> Perusahaan</h6>
                <div class="alert alert-info py-2 small mb-3" style="border-radius:8px;">
                    <i class="bi bi-info-circle me-1"></i> Data ini akan ditampilkan ke pelanggan di portal & landing
                </div>
                <div class="d-flex flex-column gap-3">
                    <input type="text" name="COMPANY_NAME" value="{{ $COMPANY_NAME ?? '' }}" placeholder="Nama Perusahaan" class="form-control">
                    <input type="email" name="COMPANY_EMAIL" value="{{ $COMPANY_EMAIL ?? '' }}" placeholder="Email Kontak" class="form-control">
                    <input type="text" name="COMPANY_ADDRESS" value="{{ $COMPANY_ADDRESS ?? '' }}" placeholder="Alamat" class="form-control">
                    <input type="text" name="COMPANY_PHONE" value="{{ $COMPANY_PHONE ?? '' }}" placeholder="No. Telepon (WhatsApp)" class="form-control">
                </div>
            </div>
        </div>

        <div class="card shadow-sm rounded-3 border-0 mb-4">
            <div class="card-body">
                <h6 class="fw-semibold mb-3 d-flex align-items-center gap-2" style="color:var(--text);"><i class="bi bi-qr-code text-info"></i> QRIS</h6>
                <div class="alert alert-info py-2 small mb-3" style="border-radius:8px;">
                    <i class="bi bi-info-circle me-1"></i> QRIS akan ditampilkan ke pelanggan untuk pembayaran
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Nama QRIS</label>
                        <input type="text" name="QRIS_NAME" value="{{ $QRIS_NAME ?? '' }}" class="form-control" placeholder="QRIS Perusahaan">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Gambar QRIS</label>
                        <input type="file" name="QRIS_IMAGE_UPLOAD" accept="image/*" class="form-control">
                        @if($QRIS_IMAGE)
                        <div class="mt-2">
                            <img src="{{ $QRIS_IMAGE }}" alt="QRIS" style="height:80px;border-radius:8px;border:1px solid var(--border);">
                            <div style="font-size:10px;color:var(--text3);margin-top:2px;">QRIS saat ini</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 rounded-3 fw-semibold shadow-sm py-3">
            <i class="bi bi-floppy me-2"></i> Simpan Pengaturan
        </button>
    </form>

    {{-- REKENING BANK -- outside main form --}}
    <div class="card shadow-sm rounded-3 border-0 mt-4 mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-semibold mb-0 d-flex align-items-center gap-2" style="color:var(--text);"><i class="bi bi-bank text-warning"></i> Rekening Bank</h6>
                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="showBankForm()"><i class="bi bi-plus me-1"></i>Tambah</button>
            </div>
            <div class="alert alert-info py-2 small mb-3" style="border-radius:8px;">
                <i class="bi bi-info-circle me-1"></i> Rekening akan ditampilkan ke pelanggan untuk pembayaran tagihan
            </div>
            @if($bankAccounts && count($bankAccounts) > 0)
            <div class="table-responsive">
                <table class="table table-sm small mb-0">
                    <thead>
                        <tr><th style="color:var(--text);">Bank</th><th style="color:var(--text);">No. Rekening</th><th style="color:var(--text);">Atas Nama</th><th class="text-end" style="color:var(--text);">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @foreach($bankAccounts as $b)
                        <tr id="bank-row-{{ $b->id }}">
                            <td style="color:var(--text);">{{ $b->bank_name }}</td>
                            <td style="color:var(--text);font-family:monospace;">{{ $b->account_number }}</td>
                            <td style="color:var(--text);">{{ $b->account_name }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary px-2 py-0" onclick="editBankRow({{ $b->id }})" style="font-size:10px;"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="/admin/my-settings/bank/delete/{{ $b->id }}" class="d-inline" onsubmit="return confirm('Hapus rekening?')">@csrf<button class="btn btn-sm btn-outline-danger px-2 py-0" style="font-size:10px;"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="small text-muted mb-0">Belum ada rekening. Klik "Tambah" untuk menambahkan.</p>
            @endif

            <div id="bankFormWrapper" style="display:none;" class="mt-3 pt-3 border-top">
                <form method="POST" action="/admin/my-settings/bank/store" id="bankForm" class="row g-2 align-items-end">
                    @csrf
                    <input type="hidden" name="edit_id" id="bankEditId" value="">
                    <div class="col-4">
                        <label class="form-label small">Bank</label>
                        <input type="text" name="bank_name" id="fBankName" required class="form-control form-control-sm" placeholder="BCA">
                    </div>
                    <div class="col-4">
                        <label class="form-label small">No. Rekening</label>
                        <input type="text" name="account_number" id="fAccountNumber" required class="form-control form-control-sm" placeholder="1234567890">
                    </div>
                    <div class="col-3">
                        <label class="form-label small">Atas Nama</label>
                        <input type="text" name="account_name" id="fAccountName" required class="form-control form-control-sm" placeholder="PT. Contoh">
                    </div>
                    <div class="col-12 col-sm-auto d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill" id="bankSubmitBtn"><i class="bi bi-check"></i> Simpan</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="hideBankForm()"><i class="bi bi-x"></i> Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Isolir Otomatis --}}
    <form method="POST" action="/admin/my-settings/save" class="mb-4">
        @csrf
        <div class="card shadow-sm rounded-3 border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-3 d-flex align-items-center gap-2" style="color:var(--text);"><i class="bi bi-shield-exclamation text-warning"></i> Isolir Otomatis</h6>
                <div class="row g-3 align-items-end">
                    <div class="col-6">
                        <label class="form-label small fw-medium">Jam Isolir</label>
                        <select name="ISOLIR_HOUR" class="form-select">
                            @for($h = 0; $h <= 23; $h++)
                            <option value="{{ $h }}" {{ ($ISOLIR_HOUR ?? '2') == $h ? 'selected' : '' }}>{{ sprintf('%02d:00', $h) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-medium">Menit</label>
                        <select name="ISOLIR_MINUTE" class="form-select">
                            @for($m = 0; $m < 60; $m += 5)
                            <option value="{{ $m }}" {{ ($ISOLIR_MINUTE ?? '0') == $m ? 'selected' : '' }}>{{ sprintf('%02d', $m) }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
                <p class="small text-muted mt-2 mb-0">Pelanggan yang melewati batas tagihan akan diisolir otomatis setiap jam ini</p>
                <button type="submit" class="btn btn-warning w-100 rounded-3 fw-semibold mt-3 py-2">
                    <i class="bi bi-floppy me-1"></i> Simpan Jadwal Isolir
                </button>
            </div>
        </div>
    </form>

    {{-- Profil Saya --}}
    <div class="card shadow-sm rounded-3 border-0 mb-4">
        <div class="card-body">
            <h6 class="fw-semibold mb-3 d-flex align-items-center gap-2" style="color:var(--text);"><i class="bi bi-person text-primary"></i> Profil Saya</h6>
            <form method="POST" action="/admin/my-settings/profile">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <input type="text" name="name" value="{{ $user->name }}" class="form-control" placeholder="Nama">
                    </div>
                    <div class="col-12 col-lg-4">
                        <input type="email" name="email" value="{{ $user->email }}" class="form-control" placeholder="Email">
                    </div>
                    <div class="col-12 col-lg-4">
                        <input type="text" name="phone" value="{{ $user->phone }}" class="form-control" placeholder="Telepon">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 rounded-3 fw-semibold mt-3 py-2">Update Profil</button>
            </form>
        </div>
    </div>

    {{-- Ganti Password --}}
    <div class="card shadow-sm rounded-3 border-0 mb-4">
        <div class="card-body">
            <h6 class="fw-semibold mb-3 d-flex align-items-center gap-2" style="color:var(--text);"><i class="bi bi-lock text-primary"></i> Ganti Password</h6>
            <form method="POST" action="/admin/my-settings/password">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <input type="password" name="current_password" required placeholder="Password Saat Ini" class="form-control">
                    </div>
                    <div class="col-12 col-lg-4">
                        <input type="password" name="new_password" required placeholder="Password Baru" class="form-control">
                    </div>
                    <div class="col-12 col-lg-4">
                        <input type="password" name="new_password_confirmation" required placeholder="Konfirmasi" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn btn-dark w-100 rounded-3 fw-semibold mt-3 py-2">Ganti Password</button>
            </form>
        </div>
    </div>
</div>

<script>
function showBankForm(data) {
    const w = document.getElementById('bankFormWrapper');
    w.style.display = 'block';
    if(data) {
        document.getElementById('bankEditId').value = data.id;
        document.getElementById('fBankName').value = data.bank_name;
        document.getElementById('fAccountNumber').value = data.account_number;
        document.getElementById('fAccountName').value = data.account_name;
        document.getElementById('bankForm').action = '/admin/my-settings/bank/update/' + data.id;
        document.getElementById('bankSubmitBtn').innerHTML = '<i class="bi bi-check-lg"></i>';
    } else {
        document.getElementById('bankEditId').value = '';
        document.getElementById('fBankName').value = '';
        document.getElementById('fAccountNumber').value = '';
        document.getElementById('fAccountName').value = '';
        document.getElementById('bankForm').action = '/admin/my-settings/bank/store';
        document.getElementById('bankSubmitBtn').innerHTML = '<i class="bi bi-check"></i>';
    }
    w.scrollIntoView({behavior:'smooth',block:'center'});
}
function hideBankForm() {
    document.getElementById('bankFormWrapper').style.display = 'none';
}
function editBankRow(id) {
    fetch('/admin/my-settings/bank/' + id + '/edit')
        .then(r => r.json())
        .then(data => showBankForm(data))
        .catch(() => alert('Gagal mengambil data'));
}
</script>
@endsection
