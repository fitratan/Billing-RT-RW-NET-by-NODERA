@extends('layouts.admin')

@section('title', 'Dashboard Teknisi')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Dashboard Teknisi</h1>
    </div>

    @if($tickets->count() > 0)
    <div class="row g-3">
        @foreach($tickets as $t)
        <div class="col-12 col-lg-6 col-xl-4">
            <div class="card shadow-sm rounded-3 border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div>
                            <p class="fw-semibold text-dark mb-1">{{ $t->customer_name }}</p>
                            <small class="text-muted">{{ $t->customer_address ?? '-' }}</small>
                        </div>
                        <span class="badge rounded-pill text-nowrap
                            {{ $t->status === 'pending' ? 'bg-warning-subtle text-warning-emphasis' : '' }}
                            {{ $t->status === 'in_progress' ? 'bg-info-subtle text-info-emphasis' : '' }}
                            {{ $t->status === 'resolved' ? 'bg-success-subtle text-success-emphasis' : '' }}">
                            {{ str_replace('_', ' ', $t->status) }}
                        </span>
                    </div>
                    <p class="small text-secondary mb-3 text-truncate">{{ $t->description }}</p>
                    <div class="mt-auto pt-3 border-top d-flex gap-2">
                        <button onclick="updateStatus({{ $t->id }}, 'in_progress')" class="btn btn-sm btn-outline-info rounded-3 flex-fill">Proses</button>
                        <button onclick="updateStatus({{ $t->id }}, 'resolved')" class="btn btn-sm btn-outline-success rounded-3 flex-fill">Selesai</button>
                        @if(!empty($t->pppoe_username))
                        <button onclick="cekOnu('{{ $t->pppoe_username }}')" class="btn btn-sm btn-outline-primary rounded-3 flex-fill">Cek ONU</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-5">
        <div class="d-inline-flex align-items-center justify-content-center bg-body-secondary rounded-circle mb-3" style="width:64px;height:64px">
            <i class="bi bi-check-circle text-muted fs-2"></i>
        </div>
        <p class="text-muted small mb-0">Tidak ada tiket yang ditugaskan</p>
    </div>
    @endif
</div>
<script>
function updateStatus(id, status) {
    if (!confirm('Update status jadi ' + status + '?')) return;
    fetch('/admin/technician/updateStatus', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: 'id=' + id + '&status=' + status
    })
    .then(r => r.json())
    .then(d => { if(d.success) location.reload(); else alert(d.message); })
    .catch(e => alert('Error'));
}
function cekOnu(pppoe) {
    fetch('/admin/technician/getOnuData?pppoe=' + encodeURIComponent(pppoe))
    .then(r => r.json()).then(d => {
        if (d.success) alert('ONU: ' + d.serial + '\nRX: ' + d.rxPower + '\nOnline: ' + (d.online ? '' : ''));
        else alert(d.message || 'ONU tidak ditemukan');
    }).catch(e => alert('Error'));
}
</script>
@endsection
