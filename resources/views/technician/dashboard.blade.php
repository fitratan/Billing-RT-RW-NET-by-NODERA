@extends('layouts.technician')
@section('title', 'Dashboard Teknisi')
@section('content')
<style>
.attendance-card{background:var(--card);border-radius:14px;padding:16px;box-shadow:var(--shadow);display:flex;align-items:center;gap:14px;margin-bottom:16px;}
.attendance-card .icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;}
.attendance-card .status-text{font-size:13px;font-weight:600;}
.attendance-card .status-time{font-size:11px;color:var(--text3);}
.stat-card{background:var(--card);border-radius:14px;padding:16px;box-shadow:var(--shadow);text-decoration:none;display:block;transition:all .15s;border:2px solid transparent;}
.stat-card:active{transform:scale(.97);}
.stat-card .stat-num{font-size:1.5rem;font-weight:700;line-height:1.1;}
.stat-card .stat-label{font-size:12px;color:var(--text2);margin-top:2px;}
.ticket-card{background:var(--card);border-radius:14px;padding:14px 16px;box-shadow:var(--shadow);margin-bottom:8px;display:flex;align-items:center;gap:12px;}
.ticket-card .title-text{font-size:14px;font-weight:600;}
.section-title{font-size:12px;color:var(--text2);font-weight:600;margin:16px 0 8px;display:flex;align-items:center;gap:6px;}
.section-title span{font-weight:400;color:var(--text3);}
.empty-state{text-align:center;padding:40px 20px;color:var(--text3);}
.empty-state i{font-size:3rem;display:block;margin-bottom:12px;}
.badge-status{font-size:10px;padding:2px 10px;border-radius:999px;font-weight:600;display:inline-block;}
</style>

<div class="container-fluid px-0" style="max-width:640px;margin:0 auto;">
    @if(session('msg'))
    <div class="alert alert-success alert-dismissible fade show small mb-3 py-2" role="alert">
        <i class="bi bi-check-circle-fill me-1"></i> {{ session('msg') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:10px;"></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show small mb-3 py-2" role="alert">
        <i class="bi bi-exclamation-circle-fill me-1"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:10px;"></button>
    </div>
    @endif

    {{-- Attendance --}}
    @php
        $todayAttendance = \App\Models\Attendance::where('collector_id', session('technician_id'))
            ->whereDate('date', today())->first();
    @endphp
    <div class="attendance-card">
        <div class="icon {{ $todayAttendance && $todayAttendance->check_out ? 'bg-secondary bg-opacity-10 text-secondary' : ($todayAttendance ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning') }}">
            <i class="bi {{ $todayAttendance && $todayAttendance->check_out ? 'bi-clock' : ($todayAttendance ? 'bi-check-circle' : 'bi-box-arrow-in-right') }}"></i>
        </div>
        <div class="flex-grow-1">
            @if($todayAttendance && $todayAttendance->check_out)
                <div class="status-text" style="color:var(--text2);">Sudah Check-out</div>
                <div class="status-time">Masuk {{ \Carbon\Carbon::parse($todayAttendance->check_in)->format('H:i') }} &middot; Keluar {{ \Carbon\Carbon::parse($todayAttendance->check_out)->format('H:i') }}</div>
            @elseif($todayAttendance)
                <div class="status-text" style="color:var(--success);">Sedang Bekerja</div>
                <div class="status-time">Check-in {{ \Carbon\Carbon::parse($todayAttendance->check_in)->format('H:i') }}</div>
            @else
                <div class="status-text" style="color:var(--warning);">Belum Check-in</div>
                <div class="status-time">Belum ada absensi hari ini</div>
            @endif
        </div>
        @if(!$todayAttendance)
        <form method="POST" action="/teknisi/check-in">
            @csrf
            <button class="btn btn-sm" style="background:var(--success);color:#fff;border:none;border-radius:8px;padding:6px 16px;font-weight:600;font-size:12px;"><i class="bi bi-box-arrow-in-right me-1"></i> Check-in</button>
        </form>
        @elseif(!$todayAttendance->check_out)
        <form method="POST" action="/teknisi/check-out">
            @csrf
            <button class="btn btn-sm" style="background:var(--danger);color:#fff;border:none;border-radius:8px;padding:6px 16px;font-weight:600;font-size:12px;"><i class="bi bi-box-arrow-left me-1"></i> Check-out</button>
        </form>
        @endif
    </div>

    {{-- Stats --}}
    <div class="row g-2 mb-3">
        <div class="col-4">
            <div class="stat-card text-center" style="{{ $stats['total'] > 0 ? 'border-color:var(--primary);' : '' }}">
                <div class="stat-num" style="color:var(--primary);">{{ $stats['total'] }}</div>
                <div class="stat-label">Total Tiket</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card text-center" style="{{ $stats['open'] > 0 ? 'border-color:var(--warning);' : '' }}">
                <div class="stat-num" style="color:var(--warning);">{{ $stats['open'] }}</div>
                <div class="stat-label">Open</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card text-center" style="{{ $stats['in_progress'] > 0 ? 'border-color:var(--info);' : '' }}">
                <div class="stat-num" style="color:var(--info);">{{ $stats['in_progress'] }}</div>
                <div class="stat-label">Progress</div>
            </div>
        </div>
    </div>

    {{-- Tickets --}}
    <div class="section-title">
        <i class="bi bi-ticket-detailed"></i>
        Tiket Pekerjaan
        <span>({{ $tickets->count() }})</span>
    </div>

    @forelse($tickets as $t)
    @php
        $statusColor = match($t->status) {
            'open' => 'warning',
            'in_progress' => 'info',
            'resolved' => 'success',
            'closed' => 'secondary',
            default => 'secondary'
        };
        $prioColor = match($t->priority ?? 'normal') {
            'high' => 'danger',
            'medium', 'normal' => 'primary',
            'low' => 'secondary',
            default => 'primary'
        };
    @endphp
    <div class="ticket-card">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge-status bg-{{ $statusColor }} bg-opacity-10 text-{{ $statusColor }}">
                    {{ str_replace('_', ' ', ucfirst($t->status)) }}
                </span>
                <span class="badge-status bg-{{ $prioColor }} bg-opacity-10 text-{{ $prioColor }}">
                    {{ ucfirst($t->priority ?? 'Normal') }}
                </span>
            </div>
            <div class="title-text">{{ $t->title ?? $t->subject ?? '-' }}</div>
            <div class="small" style="color:var(--text3);">
                #{{ $t->id }} &middot; {{ $t->created_at ? $t->created_at->diffForHumans() : '-' }}
            </div>
        </div>
    </div>
    @empty
    <div class="empty-state">
        <i class="bi bi-emoji-smile"></i>
        <p style="font-size:14px;">Tidak ada tiket. Santuy 😎</p>
    </div>
    @endforelse
</div>
@endsection
