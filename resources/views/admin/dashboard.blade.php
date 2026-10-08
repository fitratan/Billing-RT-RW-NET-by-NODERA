@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="container-fluid px-0">
    <div class="text-center py-5">
        <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width:56px;height:56px;">
            <i class="bi bi-arrow-repeat text-muted fs-4"></i>
        </div>
        <p class="small text-muted mb-0">Mengalihkan ke Dashboard...</p>
        <a href="/dashboard" class="text-primary small fw-medium mt-2 d-inline-block text-decoration-none">
            <i class="bi bi-arrow-right me-1"></i> Buka Dashboard
        </a>
    </div>
</div>
<script>window.location.href = '/dashboard';</script>
@endsection
