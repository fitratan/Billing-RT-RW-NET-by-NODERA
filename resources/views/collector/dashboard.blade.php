@extends('layouts.collector')
@section('title', 'Dashboard Kolektor')
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
.customer-card{background:var(--card);border-radius:14px;padding:14px 16px;box-shadow:var(--shadow);margin-bottom:8px;display:flex;align-items:center;gap:12px;}
.customer-card .code{font-size:11px;color:var(--text2);font-weight:500;}
.customer-card .name{font-size:14px;font-weight:600;color:var(--text);}
.customer-card .amount{font-size:13px;font-weight:600;}
.section-title{font-size:12px;color:var(--text2);font-weight:600;margin:16px 0 8px;display:flex;align-items:center;gap:6px;}
.section-title span{font-weight:400;color:var(--text3);}
.empty-state{text-align:center;padding:40px 20px;color:var(--text3);}
.empty-state i{font-size:3rem;display:block;margin-bottom:12px;}
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
        <form method="POST" action="/kolektor/check-in">
            @csrf
            <button class="btn btn-sm" style="background:var(--success);color:#fff;border:none;border-radius:8px;padding:6px 16px;font-weight:600;font-size:12px;"><i class="bi bi-box-arrow-in-right me-1"></i> Check-in</button>
        </form>
        @elseif(!$todayAttendance->check_out)
        <form method="POST" action="/kolektor/check-out">
            @csrf
            <button class="btn btn-sm" style="background:var(--danger);color:#fff;border:none;border-radius:8px;padding:6px 16px;font-weight:600;font-size:12px;"><i class="bi bi-box-arrow-left me-1"></i> Check-out</button>
        </form>
        @endif
    </div>

    {{-- Search + Filter --}}
    <form method="GET" class="mb-2">
        <div class="input-group" style="background:var(--card);border-radius:12px;overflow:hidden;box-shadow:var(--shadow);">
            <span class="input-group-text bg-transparent border-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control border-0" placeholder="Cari nama, kode, atau no. HP..." value="{{ request('search') }}" style="font-size:14px;padding:10px 0;background:transparent;">
            @if(request('search'))
            <a href="/kolektor/dashboard" class="btn btn-light border-0"><i class="bi bi-x-lg"></i></a>
            @endif
            <button class="btn btn-light border-0 text-muted" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" style="font-size:16px;background:transparent;"><i class="bi bi-funnel"></i></button>
        </div>
        <div class="collapse mt-2 {{ $packageFilter ? 'show' : '' }}" id="filterCollapse">
            <div class="d-flex gap-2 align-items-center rounded-3 p-2" style="background:var(--card);box-shadow:var(--shadow);">
                <select name="package" class="form-select form-select-sm border-0" style="font-size:13px;" onchange="this.form.submit()">
                    <option value="">Semua Paket</option>
                    @foreach($packages as $p)
                    <option value="{{ $p->id }}" {{ $packageFilter == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
                @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                @if($packageFilter)
                <a href="/kolektor/dashboard{{ request('status') ? '?status='.request('status') : '' }}" class="btn btn-sm btn-light rounded-pill" style="font-size:11px;"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </div>
    </form>

    {{-- Stats --}}
    <div class="row g-2 mb-3">
        <div class="col-4">
            <a href="/kolektor/dashboard" class="stat-card text-center {{ !$filter ? 'border border-2 border-primary' : '' }}">
                <div class="stat-num" style="color:var(--text);">{{ $totalCustomers }}</div>
                <div class="stat-label">Total</div>
            </a>
        </div>
        <div class="col-4">
            <a href="/kolektor/dashboard?status=paid" class="stat-card text-center {{ $filter==='paid' ? 'border border-2 border-success' : '' }}">
                <div class="stat-num" style="color:var(--success);">{{ $paidCount }}</div>
                <div class="stat-label">Lunas</div>
            </a>
        </div>
        <div class="col-4">
            <a href="/kolektor/dashboard?status=unpaid" class="stat-card text-center {{ $filter==='unpaid' ? 'border border-2 border-danger' : '' }}">
                <div class="stat-num" style="color:var(--danger);">{{ $unpaidCount }}</div>
                <div class="stat-label">Belum</div>
            </a>
        </div>
    </div>

    {{-- Customer List --}}
    <div class="section-title">
        <i class="bi bi-people"></i>
        {{ $filter === 'paid' ? 'Lunas' : ($filter === 'unpaid' ? 'Belum Bayar' : 'Pelanggan') }}
        <span>({{ count($customers) }})</span>
    </div>

    @forelse($customers as $c)
    @php
        $latestInvoice = $c->invoices->first();
        $isPaid = $latestInvoice && $latestInvoice->paid;
    @endphp
    <div class="customer-card">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="code">{{ $c->code }}</span>
                @if($c->collector_id)
                <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:10px;">Ditugaskan</span>
                @endif
            </div>
            <div class="name">{{ $c->name }}</div>
            <div style="font-size:11px;color:var(--text3);">
                {{ $c->package?->name ?? '-' }}
                @if($c->phone) &middot; {{ $c->phone }} @endif
            </div>
        </div>
        <div class="text-end" style="flex-shrink:0;">
            @if($latestInvoice)
                <div class="amount {{ $isPaid ? 'text-success' : 'text-danger' }}">
                    Rp{{ number_format($latestInvoice->amount, 0, ',', '.') }}
                </div>
                <div style="font-size:10px;color:var(--text3);">
                    {{ $latestInvoice->invoice_number }}
                </div>
                @if(!$isPaid)
                <form method="POST" action="/kolektor/bayar/{{ $c->id }}" style="display:inline;" onsubmit="return confirm('Konfirmasi pembayaran {{ $c->name }}?')">
                    @csrf
                    <button type="submit" class="btn btn-sm mt-1" style="background:var(--success);color:#fff;border:none;border-radius:8px;padding:4px 14px;font-weight:600;font-size:11px;"><i class="bi bi-check-lg me-1"></i>Bayar</button>
                </form>
                @else
                <span class="badge bg-success bg-opacity-10 text-success mt-1" style="font-size:10px;">Lunas</span>
                @endif
            @else
                <div style="font-size:11px;color:var(--text3);">Belum ada invoice</div>
            @endif
        </div>
    </div>
    @empty
    <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <p style="font-size:14px;">Tidak ada data pelanggan</p>
    </div>
    @endforelse
</div>
@endsection
