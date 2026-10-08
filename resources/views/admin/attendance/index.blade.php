@extends('layouts.admin')

@section('title', 'Absensi')

@section('content')
<div class="container-fluid p-3 pb-24">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Absensi <span class="text-muted fw-normal fs-6">{{ $date }}</span></h1>
        <a href="/admin/attendance/report" class="btn btn-primary px-4 py-2 rounded-3 text-white fw-semibold shadow">
            <i class="fas fa-chart-bar me-1"></i><span class="d-none d-lg-inline"> Laporan</span>
        </a>
    </div>

    

    {{-- Date Filter --}}
    <form method="GET" action="/admin/attendance" class="d-flex gap-2 mb-4">
        <input type="date" name="date" value="{{ $date }}" class="form-control flex-fill">
        <button type="submit" class="btn btn-outline-secondary px-4 rounded-3 fw-semibold">
            <i class="fas fa-filter me-1"></i> Filter
        </button>
        <button type="button" onclick="document.getElementById('checkinModal').classList.remove('d-none')" class="btn btn-primary px-4 rounded-3 fw-semibold shadow">
            <i class="fas fa-sign-in-alt me-1"></i> Check-in
        </button>
    </form>

    {{-- Stats --}}
    <div class="row g-2 g-lg-4 mb-4">
        <div class="col-4">
            <div class="card shadow-sm border-0 rounded-3 text-center h-100">
                <div class="card-body">
                    <p class="fs-5 fw-bold text-dark mb-1">{{ $stats['total'] }}</p>
                    <p class="small text-muted fw-medium mb-0">Total</p>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card shadow-sm border-0 rounded-3 text-center h-100">
                <div class="card-body">
                    <p class="fs-5 fw-bold text-dark mb-1">{{ $stats['checked_in'] }}</p>
                    <p class="small text-muted fw-medium mb-0">Masuk</p>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card shadow-sm border-0 rounded-3 text-center h-100">
                <div class="card-body">
                    <p class="fs-5 fw-bold text-dark mb-1">{{ $stats['checked_out'] }}</p>
                    <p class="small text-muted fw-medium mb-0">Pulang</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Attendance List --}}
    <div class="d-flex flex-column gap-3">
        @forelse($attendances as $att)
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center bg-light text-muted rounded-circle" style="width:40px;height:40px;">
                            <i class="fas fa-user small"></i>
                        </div>
                        <div>
                            <p class="fw-semibold text-dark mb-0">{{ $att->user?->name ?? '-' }}</p>
                            <p class="small text-muted mb-0">
                                @if($att->check_in)
                                <i class="fas fa-sign-in-alt text-success me-0-5"></i>
                                {{ \Carbon\Carbon::parse($att->check_in)->format('H:i') }}
                                @endif
                                @if($att->check_out)
                                · <i class="fas fa-sign-out-alt text-primary me-0-5"></i>
                                {{ \Carbon\Carbon::parse($att->check_out)->format('H:i') }}
                                @endif
                                @if($att->check_in && $att->check_out)
                                · <span class="text-secondary">
                                    {{ \Carbon\Carbon::parse($att->check_in)->diffInHours($att->check_out) }}j
                                    {{ \Carbon\Carbon::parse($att->check_in)->diffInMinutes($att->check_out) % 60 }}m
                                </span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if($att->check_in && !$att->check_out)
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill">
                            Aktif
                        </span>
                        <form method="POST" action="/admin/attendance/checkout/{{ $att->id }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary rounded-3 fw-semibold">
                                <i class="fas fa-sign-out-alt me-0-5"></i> Check-out
                            </button>
                        </form>
                        @elseif($att->check_out)
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">
                            Selesai
                        </span>
                        @endif
                    </div>
                </div>
                @if($att->notes)
                <div class="mt-2 small text-secondary bg-light rounded-3 p-2">
                    <i class="fas fa-sticky-note me-1"></i> {{ $att->notes }}
                </div>
                @endif
            </div>
        </div>
        @empty
        <div class="text-center py-5">
            <div class="d-flex align-items-center justify-content-center mx-auto mb-3 bg-light rounded-circle" style="width:56px;height:56px;">
                <i class="fas fa-calendar-check text-muted" style="font-size:1.25rem;"></i>
            </div>
            <p class="text-muted small">Belum ada absensi pada tanggal ini</p>
        </div>
        @endforelse
    </div>
</div>

{{-- Check-in Modal --}}
<div id="checkinModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Check-in Karyawan</h5>
                <button type="button" class="btn-close" onclick="document.getElementById('checkinModal').classList.remove('show'); document.getElementById('checkinModal').style.display='none'"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/attendance/checkin">
                    @csrf
                    <div class="mb-3">
                        <select name="user_id" required class="form-select">
                            <option value="">Pilih Karyawan</option>
                            @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="date" value="{{ $date }}">
                    <div class="mb-3">
                        <textarea name="notes" rows="2" placeholder="Catatan (opsional)" class="form-control"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">Check-in</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection