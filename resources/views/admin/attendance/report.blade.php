@extends('layouts.print')

@section('title', 'Laporan Absensi')

@section('content')
<div class="container-fluid p-3 pb-24">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Laporan Absensi</h1>
        <a href="/admin/attendance" class="btn btn-outline-secondary btn-sm px-4 py-2 rounded-3 fw-semibold">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <form method="GET" action="/admin/attendance/report" class="d-flex gap-2 mb-4">
        <input type="date" name="start_date" value="{{ $startDate }}" class="form-control flex-fill">
        <input type="date" name="end_date" value="{{ $endDate }}" class="form-control flex-fill">
        <button type="submit" class="btn btn-outline-secondary px-4 rounded-3 fw-semibold">
            <i class="fas fa-filter me-1"></i> Filter
        </button>
    </form>

    @forelse($users as $user)
    <div class="card shadow-sm border-0 rounded-3 mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center" style="width:40px;height:40px;border-radius:50%;background:#e8f0fe;color:#1a73e8;">
                        <i class="fas fa-user small"></i>
                    </div>
                    <div>
                        <p class="fw-semibold text-dark mb-0">{{ $user->name }}</p>
                        <p class="small text-muted text-capitalize mb-0">{{ $user->role }}</p>
                    </div>
                </div>
                <span class="small text-muted">
                    {{ isset($attendances[$user->id]) ? $attendances[$user->id]->count() : 0 }} hari
                </span>
            </div>

            @if(isset($attendances[$user->id]) && $attendances[$user->id]->count() > 0)
            <div class="d-flex flex-column gap-2">
                @foreach($attendances[$user->id] as $att)
                <div class="d-flex align-items-center justify-content-between bg-light rounded-3 p-3">
                    <div>
                        <p class="small fw-medium text-dark mb-0">{{ \Carbon\Carbon::parse($att->date)->format('d M Y') }}</p>
                        <p class="small text-muted mb-0">
                            @if($att->check_in)
                            <i class="fas fa-sign-in-alt text-success me-0-5"></i>{{ \Carbon\Carbon::parse($att->check_in)->format('H:i') }}
                            @endif
                            @if($att->check_out)
                            · <i class="fas fa-sign-out-alt text-primary me-0-5"></i>{{ \Carbon\Carbon::parse($att->check_out)->format('H:i') }}
                            @endif
                            @if($att->check_in && $att->check_out)
                            · {{ \Carbon\Carbon::parse($att->check_in)->diffInHours($att->check_out) }}j
                            {{ \Carbon\Carbon::parse($att->check_in)->diffInMinutes($att->check_out) % 60 }}m
                            @endif
                        </p>
                    </div>
                    @if($att->notes)
                    <span class="small text-muted text-truncate" style="max-width:150px;" title="{{ $att->notes }}">
                        <i class="fas fa-sticky-note me-0-5"></i>{{ $att->notes }}
                    </span>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <p class="small text-muted text-center py-3 mb-0">Tidak ada data absensi</p>
            @endif
        </div>
    </div>
    @empty
    <div class="text-center py-5">
        <div class="d-flex align-items-center justify-content-center mx-auto mb-3" style="width:56px;height:56px;border-radius:50%;background:#f3f4f6;">
            <i class="fas fa-users text-muted" style="font-size:1.25rem;"></i>
        </div>
        <p class="text-muted small">Tidak ada karyawan</p>
    </div>
    @endforelse
</div>
@endsection
