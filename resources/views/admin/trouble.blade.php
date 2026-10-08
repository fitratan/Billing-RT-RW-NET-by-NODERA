@extends('layouts.admin')

@section('title', 'Laporan Gangguan')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Laporan Gangguan</h1>
        <button type="button" class="btn btn-primary shadow-sm rounded-3" data-bs-toggle="modal" data-bs-target="#ticketModal">
            <i class="bi bi-plus-lg me-1"></i> Baru
        </button>
    </div>

    <div class="row g-3">
        @forelse($tickets as $t)
        <div class="col-12">
            <div class="card shadow-sm rounded-3 border-0">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <span class="small text-muted font-monospace">#{{ $t->id }}</span>
                                <span class="badge rounded-pill
                                    {{ $t->status === 'pending' ? 'bg-warning-subtle text-warning-emphasis' : '' }}
                                    {{ $t->status === 'in_progress' ? 'bg-info-subtle text-info-emphasis' : '' }}
                                    {{ $t->status === 'resolved' ? 'bg-success-subtle text-success-emphasis' : '' }}
                                    {{ $t->status === 'closed' ? 'bg-secondary-subtle text-secondary-emphasis' : '' }}">
                                    {{ str_replace('_', ' ', $t->status) }}
                                </span>
                                <span class="badge rounded-pill
                                    {{ $t->priority === 'low' ? 'bg-secondary-subtle text-secondary-emphasis' : '' }}
                                    {{ $t->priority === 'medium' ? 'bg-warning-subtle text-warning-emphasis' : '' }}
                                    {{ $t->priority === 'high' ? 'bg-warning-subtle text-warning-emphasis' : '' }}
                                    {{ $t->priority === 'urgent' ? 'bg-danger-subtle text-danger-emphasis' : '' }}">
                                    {{ ucfirst($t->priority) }}
                                </span>
                            </div>
                            <p class="fw-semibold text-dark mb-1">{{ $t->customer_name }}</p>
                            <p class="small text-secondary mb-1 text-truncate">{{ $t->description }}</p>
                            @if(!empty($t->technician_name))
                            <p class="small text-muted mt-1"><i class="bi bi-person-gear me-1"></i>{{ $t->technician_name }}</p>
                            @endif
                        </div>
                        <span class="small text-muted text-nowrap ms-3">{{ \Carbon\Carbon::parse($t->created_at)->diffForHumans() }}</span>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="text-center py-5">
                <div class="d-inline-flex align-items-center justify-content-center bg-body-secondary rounded-circle" style="width:64px;height:64px">
                    <i class="bi bi-check-circle text-muted fs-2"></i>
                </div>
                <p class="text-muted small mt-3 mb-0">Tidak ada laporan gangguan</p>
            </div>
        </div>
        @endforelse
    </div>
</div>

{{-- Create Modal --}}
<div class="modal fade" id="ticketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-sm border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Tiket Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/trouble/create">
                    @csrf
                    <div class="mb-3">
                        <select name="customer_id" required class="form-select">
                            <option value="">Pilih pelanggan...</option>
                            @foreach($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->pppoe_username ?? $c->phone }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <textarea name="description" required rows="3" class="form-control" placeholder="Jelaskan keluhan..."></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <select name="priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <select name="assigned_to" class="form-select">
                                <option value="">— Pilih Teknisi —</option>
                                @foreach($technicians as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-3 fw-semibold">Buat Tiket</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
