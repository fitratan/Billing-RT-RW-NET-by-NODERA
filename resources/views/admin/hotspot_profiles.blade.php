@extends('layouts.admin')
@section('title', 'Hotspot Profiles')
@section('content')
<div class="container-fluid px-0 pb-5">
    <h4 class="fw-bold text-dark mb-4">Hotspot Profiles</h4>
    @if(count($profiles) > 0)
    <div class="row row-cols-1 row-cols-md-3 g-3">
        @foreach($profiles as $p)
        <div class="col">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <span class="fw-semibold text-dark">{{ is_array($p) ? $p['name'] : $p->name ?? $p }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-5">
        <p class="text-muted small">Tidak ada hotspot profile</p>
    </div>
    @endif
</div>
@endsection
