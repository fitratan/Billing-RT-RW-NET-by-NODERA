@extends('layouts.admin')

@section('title', 'Backup')

@section('content')
<div class="container-fluid p-3 pb-24">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Backup & Recovery</h1>
        <button onclick="location.reload()" class="btn btn-outline-secondary rounded-3 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Stats --}}
    <div class="row g-2 g-lg-4 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <p class="small text-muted fw-medium mb-1">Total Backup</p>
                    <p class="fs-5 fw-bold text-dark mb-1">{{ count($backups) }}</p>
                    <p class="small text-muted mb-0">File tersimpan</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <p class="small text-muted fw-medium mb-1">Database</p>
                    <p class="fs-5 fw-bold text-dark mb-1">{{ $dbCount }}</p>
                    <p class="small text-muted mb-0">Backup database</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <p class="small text-muted fw-medium mb-1">Full Backup</p>
                    <p class="fs-5 fw-bold text-dark mb-1">{{ $fullCount }}</p>
                    <p class="small text-muted mb-0">Termasuk env</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <p class="small text-muted fw-medium mb-1">Total Size</p>
                    <p class="fs-5 fw-bold text-dark mb-1">{{ $totalSize > 0 ? round($totalSize / 1048576, 1) : 0 }}<span class="fs-6 text-muted fw-normal"> MB</span></p>
                    <p class="small text-muted mb-0">Kapasitas digunakan</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <h2 class="fw-semibold text-dark mb-4 d-flex align-items-center gap-2" style="font-size:0.9rem;">
                        <i class="fas fa-plus-circle text-primary"></i> Buat Backup Baru
                    </h2>
                    <form action="/admin/backup/create" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small text-muted">Tipe Backup</label>
                            <select name="type" class="form-select">
                                <option value="database">Database Saja</option>
                                <option value="full">Full (Database + .env)</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow">
                            <i class="fas fa-cloud-upload-alt"></i> Mulai Backup
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <h2 class="fw-semibold text-dark mb-4 d-flex align-items-center gap-2" style="font-size:0.9rem;">
                        <i class="fas fa-info-circle text-primary"></i> Informasi
                    </h2>
                    <div class="d-flex flex-column gap-1 small text-secondary">
                        <div class="d-flex justify-content-between py-1 border-bottom border-light">
                            <span class="text-muted">Lokasi Backup</span>
                            <span class="fw-medium text-dark small">storage/app/backups/</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom border-light">
                            <span class="text-muted">Database</span>
                            <span class="fw-medium text-dark small">SQLite</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Backup otomatis</span>
                            <span class="fw-medium text-dark small">Manual (on-demand)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Backup List --}}
    <div class="card shadow-sm border-0 rounded-3 overflow-hidden">
        <div class="card-header bg-transparent border-bottom border-light">
            <h2 class="fw-semibold text-dark mb-0" style="font-size:0.9rem;"><i class="fas fa-history text-primary me-2"></i>Riwayat Backup</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="fw-semibold">Nama File</th>
                        <th class="text-center fw-semibold">Tipe</th>
                        <th class="text-end fw-semibold">Ukuran</th>
                        <th class="fw-semibold">Tanggal</th>
                        <th class="text-end fw-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($backups as $backup)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-file-archive text-primary"></i>
                                <span class="fw-medium text-dark small">{{ $backup['fileName'] }}</span>
                            </div>
                        </td>
                        <td class="text-center">
                            @if($backup['type'] === 'database')
                            <span class="badge bg-success">
                                <i class="fas fa-database me-1"></i> Database
                            </span>
                            @elseif($backup['type'] === 'full')
                            <span class="badge bg-primary">
                                <i class="fas fa-hdd me-1"></i> Full
                            </span>
                            @else
                            <span class="badge bg-light text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end text-secondary small">{{ $backup['sizeFormatted'] }}</td>
                        <td class="text-muted small">{{ $backup['createdFormatted'] }}</td>
                        <td class="text-end">
                            <div class="d-flex align-items-center justify-content-end gap-1">
                                <a href="/admin/backup/download/{{ $backup['fileName'] }}" class="btn btn-sm btn-outline-success" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <form action="/admin/backup/delete/{{ $backup['fileName'] }}" method="POST" onsubmit="return confirm('Hapus backup {{ $backup['fileName'] }}?')" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="fas fa-inbox" style="font-size:2rem;color:#e5e7eb;"></i>
                            <p class="small mt-2 mb-0">Belum ada file backup</p>
                            <p class="small text-muted mt-1">Buat backup baru menggunakan form di atas</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
