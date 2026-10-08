@extends('layouts.technician')
@section('title', 'Antrian Pekerjaan')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/teknisi/dashboard" class="btn btn-sm btn-outline-secondary rounded-3"><i class="bi bi-arrow-left"></i></a>
        <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-inboxes text-warning me-2"></i>Antrian Pekerjaan</h1>
    </div>

    {{-- Tiket Baru (pending) --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-transparent border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0"><i class="bi bi-envelope text-warning me-1"></i>Tiket Baru ({{ $openTickets->count() }})</h6>
        </div>
        <div class="card-body p-0">
            @if($openTickets->count() > 0)
            <div class="list-group list-group-flush">
                @foreach($openTickets as $t)
                <div class="list-group-item border-0 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="fw-semibold mb-0 small">{{ $t->title }}</p>
                            <p class="text-muted mb-0" style="font-size:10px;">{{ $t->customer_name ?? '-' }} · {{ \Carbon\Carbon::parse($t->created_at)->diffForHumans() }}</p>
                        </div>
                        <span class="badge bg-warning bg-opacity-10 text-warning" style="font-size:9px;">Pending</span>
                    </div>
                    @if($t->description)
                    <p class="small text-muted mt-1 mb-0">{{ $t->description }}</p>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-4 text-muted"><p class="small mb-0">Tidak ada tiket baru</p></div>
            @endif
        </div>
    </div>

    {{-- Tiket Diproses --}}
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-transparent border-0 pt-3 px-3 pb-0">
            <h6 class="fw-bold mb-0"><i class="bi bi-gear text-primary me-1"></i>Sedang Diproses ({{ $inProgressTickets->count() }})</h6>
        </div>
        <div class="card-body p-0">
            @if($inProgressTickets->count() > 0)
            <div class="list-group list-group-flush">
                @foreach($inProgressTickets as $t)
                <div class="list-group-item border-0 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="fw-semibold mb-0 small">{{ $t->title }}</p>
                            <p class="text-muted mb-0" style="font-size:10px;">{{ $t->customer_name ?? '-' }} · {{ \Carbon\Carbon::parse($t->created_at)->diffForHumans() }}</p>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:9px;">Diproses</span>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-4 text-muted"><p class="small mb-0">Tidak ada tiket diproses</p></div>
            @endif
        </div>
    </div>
</div>
@endsection
