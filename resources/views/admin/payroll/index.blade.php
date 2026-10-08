@extends('layouts.admin')

@section('title', 'Payroll')

@section('content')
<div class="container-fluid p-3 pb-24">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Payroll</h1>
        <button onclick="document.getElementById('payrollModal').classList.remove('d-none')" class="btn btn-primary px-4 py-2 rounded-3 text-white fw-semibold shadow">
            <i class="fas fa-plus me-1"></i> Baru
        </button>
    </div>

    

    {{-- Period Filter --}}
    <form method="GET" action="/admin/payroll" class="d-flex gap-2 mb-4">
        <select name="month" class="form-select w-auto">
            @foreach(range(1, 12) as $m)
            <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
            @endforeach
        </select>
        <input type="number" name="year" value="{{ $year }}" class="form-control" style="width:100px;">
        <button type="submit" class="btn btn-outline-secondary px-4 rounded-3 fw-semibold">
            <i class="fas fa-filter me-1"></i> Filter
        </button>
    </form>

    {{-- Stats Summary --}}
    <div class="row g-2 g-lg-4 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <p class="small text-muted fw-medium mb-1">Total Gaji</p>
                    <p class="fs-5 fw-bold text-dark mb-0">Rp {{ number_format($summary['total_salary'], 0, ',', '.') }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <p class="small text-muted fw-medium mb-1">Total Bonus</p>
                    <p class="fs-5 fw-bold text-dark mb-0">Rp {{ number_format($summary['total_bonus'], 0, ',', '.') }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <p class="small text-muted fw-medium mb-1">Total Potongan</p>
                    <p class="fs-5 fw-bold text-dark mb-0">Rp {{ number_format($summary['total_deductions'], 0, ',', '.') }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <p class="small text-muted fw-medium mb-1">Total Bersih</p>
                    <p class="fs-5 fw-bold text-dark mb-0">Rp {{ number_format($summary['total_net'], 0, ',', '.') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Sub Stats: Paid / Unpaid --}}
    <div class="d-flex gap-2 mb-4">
        <div class="px-3 py-1 rounded-3 bg-success bg-opacity-10 text-success small fw-semibold">
            <i class="fas fa-check-circle me-1"></i> Dibayar: {{ $summary['paid_count'] }}
        </div>
        <div class="px-3 py-1 rounded-3 bg-warning bg-opacity-10 text-warning small fw-semibold">
            <i class="fas fa-clock me-1"></i> Belum: {{ $summary['unpaid_count'] }}
        </div>
    </div>

    {{-- Payroll List --}}
    <div class="d-flex flex-column gap-3">
        @forelse($payrolls as $p)
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width:40px;height:40px;background:#eef2ff;color:#6366f1;">
                            <i class="fas fa-user-tie small"></i>
                        </div>
                        <div>
                            <p class="fw-semibold text-dark mb-0">{{ $p->user?->name ?? '-' }}</p>
                            <p class="small text-muted mb-0">
                                {{ $p->period_month }}/{{ $p->period_year }}
                                @if($p->paid_at)
                                · <span class="text-success">Lunas {{ \Carbon\Carbon::parse($p->paid_at)->format('d/m/Y') }}</span>
                                @else
                                · <span class="text-warning">Belum dibayar</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="text-end">
                        <p class="fw-bold text-dark mb-0">Rp {{ number_format($p->total, 0, ',', '.') }}</p>
                        @if($p->paid_at)
                        <span class="badge bg-success rounded-pill">Paid</span>
                        @else
                        <span class="badge bg-warning text-dark rounded-pill">Unpaid</span>
                        @endif
                    </div>
                </div>

                <div class="row g-2 small mb-3">
                    <div class="col-4">
                        <div class="bg-light rounded-3 p-2">
                            <span class="text-muted d-block">Gaji</span>
                            <span class="fw-semibold text-dark">Rp {{ number_format($p->salary, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-light rounded-3 p-2">
                            <span class="text-muted d-block">Bonus</span>
                            <span class="fw-semibold text-success">+Rp {{ number_format($p->bonus, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-light rounded-3 p-2">
                            <span class="text-muted d-block">Potongan</span>
                            <span class="fw-semibold text-danger">-Rp {{ number_format($p->deductions, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 pt-3 border-top border-light">
                    @if(!$p->paid_at)
                    <form method="POST" action="/admin/payroll/pay/{{ $p->id }}" class="flex-fill" onsubmit="return confirm('Tandai payroll ini sebagai dibayar?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-success w-100 py-1 small fw-semibold rounded-3">
                            <i class="fas fa-check-circle me-1"></i> Tandai Dibayar
                        </button>
                    </form>
                    <form method="POST" action="/admin/payroll/delete/{{ $p->id }}" class="flex-fill" onsubmit="return confirm('Hapus payroll ini?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100 py-1 small fw-semibold rounded-3">
                            <i class="fas fa-trash me-1"></i> Hapus
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-5">
            <div class="d-flex align-items-center justify-content-center mx-auto mb-3 bg-light rounded-circle" style="width:56px;height:56px;">
                <i class="fas fa-wallet text-muted" style="font-size:1.25rem;"></i>
            </div>
            <p class="text-muted small">Belum ada data payroll untuk periode {{ \Carbon\Carbon::create()->month($month)->format('F') }} {{ $year }}</p>
        </div>
        @endforelse
    </div>
</div>

{{-- Create Payroll Modal --}}
<div id="payrollModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Payroll Baru</h5>
                <button type="button" class="btn-close" onclick="document.getElementById('payrollModal').classList.remove('show'); document.getElementById('payrollModal').style.display='none'"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/payroll/create">
                    @csrf
                    <div class="mb-3">
                        <select name="user_id" required class="form-select">
                            <option value="">Pilih Karyawan</option>
                            @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <select name="period_month" class="form-select">
                                @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <input type="number" name="period_year" value="{{ $year }}" class="form-control" placeholder="Tahun">
                        </div>
                    </div>
                    <div class="mb-3">
                        <input type="number" step="0.01" name="salary" required placeholder="Gaji Pokok" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="number" step="0.01" name="bonus" placeholder="Bonus (opsional)" value="0" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="number" step="0.01" name="deductions" placeholder="Potongan (opsional)" value="0" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection