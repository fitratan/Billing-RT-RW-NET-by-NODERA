@extends('layouts.admin')
@section('title', 'Peta ONU')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Peta ONU</h1>
        <a href="/admin/api/onuLocations" target="_blank" class="btn btn-outline-secondary rounded-3 shadow-sm border">
            <i class="bi bi-geo-alt me-1"></i> API Data
        </a>
    </div>
    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-body text-center py-5">
            <i class="bi bi-map text-secondary" style="font-size:3rem"></i>
            <p class="text-muted small mt-3 mb-0">
                Peta ONU membutuhkan integrasi Leaflet/Mapbox.<br>Data lokasi tersedia via API.
            </p>
        </div>
    </div>
</div>
@endsection
