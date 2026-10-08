@extends('layouts.admin')

@section('title', 'Laporan Keuangan')

@section('content')
<div class="container-fluid px-0">
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold text-dark mb-0">Laporan Keuangan</h1>
            <p class="small text-muted mb-0">Laporan pendapatan & pengeluaran</p>
        </div>
    </div>

    {{-- Alerts --}}
    

    {{-- Period Filter --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            <form method="GET" action="/admin/finance" class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted">Bulan:</label>
                    <select name="month" class="form-select form-select-sm w-auto">
                        @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted">Tahun:</label>
                    <select name="year" class="form-select form-select-sm w-auto">
                        @for($y = date('Y'); $y >= date('Y')-3; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> Tampilkan
                </button>
                <a href="/admin/finance/print?year={{ $year }}&month={{ $month }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-filetype-pdf"></i> Cetak PDF
                </a>
            </form>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-3 bg-success bg-opacity-10 text-success" style="width:44px;height:44px;flex-shrink:0;">
                        <i class="bi bi-arrow-up"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Pendapatan</div>
                        <div class="fs-5 fw-bold text-success">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-3 bg-danger bg-opacity-10 text-danger" style="width:44px;height:44px;flex-shrink:0;">
                        <i class="bi bi-arrow-down"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Pengeluaran</div>
                        <div class="fs-5 fw-bold text-danger">Rp {{ number_format($totalExpenses, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-3 {{ $profitLoss >= 0 ? 'bg-primary bg-opacity-10 text-primary' : 'bg-danger bg-opacity-10 text-danger' }}" style="width:44px;height:44px;flex-shrink:0;">
                        <i class="bi {{ $profitLoss >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow' }}"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Laba Bersih</div>
                        <div class="fs-5 fw-bold {{ $profitLoss >= 0 ? 'text-primary' : 'text-danger' }}">
                            Rp {{ number_format($profitLoss, 0, ',', '.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Daily Revenue Progress Bars --}}
    @if(isset($dailyRevenue) && $dailyRevenue->count() > 0)
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-fill me-2 text-primary"></i> Pendapatan Harian</h5>
            @php $maxRev = max($dailyRevenue->pluck('total')->toArray() ?: [1]); @endphp
            <div class="d-flex flex-column gap-2">
                @foreach($dailyRevenue as $day)
                <div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">{{ \Carbon\Carbon::parse($day->date)->format('d/m') }}</span>
                        <span class="fw-semibold text-success">Rp{{ number_format($day->total, 0, ',', '.') }}</span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ ($day->total / $maxRev) * 100 }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Expense Breakdown & Recent --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart-fill me-2 text-primary"></i> Pengeluaran per Kategori</h5>
                    @if($categoryBreakdown->count() > 0)
                    <div class="d-flex flex-column gap-2">
                        @php $maxCat = max($categoryBreakdown->pluck('total')->toArray() ?: [1]); @endphp
                        @foreach($categoryBreakdown as $cat)
                        <div>
                            <div class="d-flex justify-content-between small">
                                <span class="text-secondary">{{ $cat->category }}</span>
                                <span class="fw-semibold">Rp {{ number_format($cat->total, 0, ',', '.') }}</span>
                            </div>
                            <div class="progress mt-1" style="height:8px;">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: {{ ($cat->total / $maxCat) * 100 }}%"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="small text-muted text-center py-4 mb-0">Belum ada pengeluaran bulan ini.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-receipt me-2 text-primary"></i> Pengeluaran Terbaru</h5>
                        <a href="/admin/finance/expenses" class="small text-primary fw-medium">Lihat Semua →</a>
                    </div>
                    @if($expenses->count() > 0)
                    <div class="d-flex flex-column gap-2">
                        @foreach($expenses->take(5) as $exp)
                        <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-light">
                            <div>
                                <p class="small fw-medium text-dark mb-0">{{ Str::limit($exp->description, 40) }}</p>
                                <p class="small text-muted mb-0">{{ \Carbon\Carbon::parse($exp->date)->format('d M Y') }} · {{ $exp->category }}</p>
                            </div>
                            <span class="small fw-semibold text-danger">-Rp{{ number_format($exp->amount, 0, ',', '.') }}</span>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="small text-muted text-center py-4 mb-0">Belum ada pengeluaran.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Revenue --}}
    @if($recentRevenue->count() > 0)
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history me-2 text-primary"></i> Pendapatan Terbaru</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-semibold">No. Invoice</th>
                            <th class="fw-semibold">Pelanggan</th>
                            <th class="fw-semibold">Tanggal Bayar</th>
                            <th class="fw-semibold text-end">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentRevenue as $inv)
                        <tr>
                            <td>{{ $inv->invoice_number ?? 'INV-'.$inv->id }}</td>
                            <td>{{ $inv->customer_name }}</td>
                            <td>{{ \Carbon\Carbon::parse($inv->paid_at)->format('d M Y H:i') }}</td>
                            <td class="text-end fw-semibold text-success">Rp{{ number_format($inv->amount, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection