@extends('layouts.admin')
@section('title', 'Voucher')
@section('content')
<div class="container-fluid p-3 pb-24">
    <h1 class="fs-4 fw-bold text-dark mb-4">Voucher Hotspot</h1>
    @if(count($vouchers) > 0)
    <div class="row row-cols-1 row-cols-lg-3 g-3">
        @foreach($vouchers as $v)
        <div class="col">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <p class="fw-semibold text-dark mb-1">{{ is_array($v) ? $v['name'] ?? '-' : $v->name ?? '-' }}</p>
                    <p class="small text-muted mb-0">{{ is_array($v) ? $v['profile'] ?? '-' : $v->profile ?? '-' }}</p>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-5"><p class="text-muted small">Belum ada voucher</p></div>
    @endif
</div>
@endsection
