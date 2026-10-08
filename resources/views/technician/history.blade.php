@extends('layouts.technician')
@section('title', 'Riwayat Kerja')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/teknisi/dashboard" class="btn btn-sm btn-outline-secondary rounded-3"><i class="bi bi-arrow-left"></i></a>
        <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Riwayat Kerja</h1>
    </div>

    {{-- Tiket Selesai --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-transparent border-0 pt-3 px-3 pb-0">
            <h6 class="fw-bold mb-0"><i class="bi bi-check-circle text-success me-1"></i>Tiket Terselesaikan ({{ $resolvedTickets->count() }})</h6>
        </div>
        <div class="card-body p-0">
            @if($resolvedTickets->count() > 0)
            <div class="list-group list-group-flush">
                @foreach($resolvedTickets as $t)
                <div class="list-group-item border-0 d-flex justify-content-between align-items-center py-3">
                    <div>
                        <p class="fw-semibold mb-0 small">{{ $t->title }}</p>
                        <p class="text-muted mb-0" style="font-size:10px;">{{ $t->customer_name }} · {{ \Carbon\Carbon::parse($t->resolved_at)->format('d M Y H:i') }}</p>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success" style="font-size:9px;">Selesai</span>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-4 text-muted"><p class="small mb-0">Belum ada tiket selesai</p></div>
            @endif
        </div>
    </div>

    {{-- Absensi --}}
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-transparent border-0 pt-3 px-3 pb-0">
            <h6 class="fw-bold mb-0"><i class="bi bi-clipboard-check text-info me-1"></i>Riwayat Absensi ({{ $attendance->count() }})</h6>
        </div>
        <div class="card-body p-0">
            @if($attendance->count() > 0)
            <div class="list-group list-group-flush">
                @foreach($attendance as $a)
                <div class="list-group-item border-0 d-flex justify-content-between align-items-center py-2">
                    <div>
                        <p class="small mb-0">{{ \Carbon\Carbon::parse($a->created_at)->format('d M Y') }}</p>
                        <p class="text-muted mb-0" style="font-size:10px;">
                            Check-in: {{ $a->check_in ? \Carbon\Carbon::parse($a->check_in)->format('H:i') : '-' }} |
                            Check-out: {{ $a->check_out ? \Carbon\Carbon::parse($a->check_out)->format('H:i') : '-' }}
                        </p>
                    </div>
                    <span class="badge bg-{{ $a->check_out ? 'success' : 'warning' }} bg-opacity-10 text-{{ $a->check_out ? 'success' : 'warning' }}" style="font-size:9px;">
                        {{ $a->check_out ? 'Selesai' : 'Aktif' }}
                    </span>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-4 text-muted"><p class="small mb-0">Belum ada absensi</p></div>
            @endif
        </div>
    </div>
</div>
@endsection
