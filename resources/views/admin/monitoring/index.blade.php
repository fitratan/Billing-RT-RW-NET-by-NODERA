@extends('layouts.admin')

@section('title', 'Monitoring')

@section('content')
<div class="container-fluid p-3 pb-24">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Monitoring Sistem</h1>
        <button onclick="location.reload()" class="btn btn-outline-secondary rounded-3 d-flex align-items-center justify-content-center gap-1" style="height:40px;">
            <i class="fas fa-sync-alt"></i> <span class="d-none d-lg-inline small ms-1">Refresh</span>
        </button>
    </div>

    {{-- Health Status --}}
    <div class="card shadow-sm border-0 rounded-3 text-center mb-4">
        <div class="card-body py-4">
            @php
                $statusColors = ['healthy' => 'success', 'warning' => 'warning', 'critical' => 'danger'];
                $statusIcons = ['healthy' => 'check-circle', 'warning' => 'exclamation-triangle', 'critical' => 'times-circle'];
                $sc = $statusColors[$healthStatus['status']] ?? 'secondary';
                $si = $statusIcons[$healthStatus['status']] ?? 'question-circle';
            @endphp
            <p class="small fw-semibold text-muted text-uppercase mb-3">Status Kesehatan Sistem</p>
            <div class="d-inline-flex align-items-center gap-2 px-4 py-2 rounded-3 fw-bold fs-5 border bg-{{ $sc }} bg-opacity-10 text-{{ $sc }} border-{{ $sc }} border-opacity-25">
                <i class="fas fa-{{ $si }}"></i>
                {{ strtoupper($healthStatus['status']) }}
            </div>
            <p class="small text-muted mt-3 mb-0">Update: {{ \Carbon\Carbon::parse($healthStatus['timestamp'])->format('d M Y H:i:s') }}</p>
        </div>
    </div>

    {{-- Issues & Warnings --}}
    @if(count($healthStatus['issues']) > 0 || count($healthStatus['warnings']) > 0)
    <div class="row g-4 mb-4">
        @if(count($healthStatus['issues']) > 0)
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 rounded-3 border-start border-danger border-4">
                <div class="card-body">
                    <h3 class="fw-semibold text-danger mb-3 d-flex align-items-center gap-2" style="font-size:0.9rem;">
                        <i class="fas fa-exclamation-circle"></i> Issues ({{ count($healthStatus['issues']) }})
                    </h3>
                    <div class="d-flex flex-column gap-2">
                        @foreach($healthStatus['issues'] as $issue)
                        <div class="p-3 bg-danger bg-opacity-10 rounded-3 small text-danger d-flex align-items-start gap-2">
                            <i class="fas fa-times-circle mt-0-5 text-danger"></i>
                            <span>{{ $issue }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if(count($healthStatus['warnings']) > 0)
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 rounded-3 border-start border-warning border-4">
                <div class="card-body">
                    <h3 class="fw-semibold text-warning mb-3 d-flex align-items-center gap-2" style="font-size:0.9rem;">
                        <i class="fas fa-exclamation-triangle"></i> Warnings ({{ count($healthStatus['warnings']) }})
                    </h3>
                    <div class="d-flex flex-column gap-2">
                        @foreach($healthStatus['warnings'] as $warning)
                        <div class="p-3 bg-warning bg-opacity-10 rounded-3 small text-warning d-flex align-items-start gap-2">
                            <i class="fas fa-exclamation-circle mt-0-5 text-warning"></i>
                            <span>{{ $warning }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- System Metrics --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            <h2 class="fw-semibold text-dark mb-4 d-flex align-items-center gap-2" style="font-size:0.9rem;">
                <i class="fas fa-microchip text-primary"></i> Metrik Sistem
            </h2>
            <div class="row g-3 g-lg-4">
                {{-- CPU --}}
                <div class="col-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <p class="small text-muted fw-medium text-uppercase mb-1">CPU Usage</p>
                        <p class="fs-3 fw-bold text-dark mb-0">{{ $healthStatus['metrics']['cpu']['percentage'] }}%</p>
                        <div class="mt-2 progress" style="height:8px;">
                            @php $cpuPct = $healthStatus['metrics']['cpu']['percentage']; @endphp
                            <div class="progress-bar {{ $cpuPct > 80 ? 'bg-danger' : ($cpuPct > 60 ? 'bg-warning' : 'bg-success') }}" role="progressbar" style="width: {{ min(100, $cpuPct) }}%" aria-valuenow="{{ $cpuPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <p class="small text-muted mt-1 mb-0">{{ $healthStatus['metrics']['cpu']['cores'] }} cores</p>
                    </div>
                </div>

                {{-- Memory --}}
                <div class="col-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <p class="small text-muted fw-medium text-uppercase mb-1">Memory Usage</p>
                        <p class="fs-3 fw-bold text-dark mb-0">{{ $healthStatus['metrics']['memory']['percentage'] }}%</p>
                        <div class="mt-2 progress" style="height:8px;">
                            @php $memPct = $healthStatus['metrics']['memory']['percentage']; @endphp
                            <div class="progress-bar {{ $memPct > 80 ? 'bg-danger' : ($memPct > 60 ? 'bg-warning' : 'bg-success') }}" role="progressbar" style="width: {{ min(100, $memPct) }}%" aria-valuenow="{{ $memPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <p class="small text-muted mt-1 mb-0">{{ $healthStatus['metrics']['memory']['used'] }} / {{ $healthStatus['metrics']['memory']['total'] }}</p>
                    </div>
                </div>

                {{-- Disk --}}
                <div class="col-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <p class="small text-muted fw-medium text-uppercase mb-1">Disk Usage</p>
                        <p class="fs-3 fw-bold text-dark mb-0">{{ $healthStatus['metrics']['disk']['percentage'] }}%</p>
                        <div class="mt-2 progress" style="height:8px;">
                            @php $diskPct = $healthStatus['metrics']['disk']['percentage']; @endphp
                            <div class="progress-bar {{ $diskPct > 80 ? 'bg-danger' : ($diskPct > 60 ? 'bg-warning' : 'bg-success') }}" role="progressbar" style="width: {{ min(100, $diskPct) }}%" aria-valuenow="{{ $diskPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <p class="small text-muted mt-1 mb-0">{{ $healthStatus['metrics']['disk']['used'] }} / {{ $healthStatus['metrics']['disk']['total'] }}</p>
                    </div>
                </div>

                {{-- Uptime --}}
                <div class="col-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <p class="small text-muted fw-medium text-uppercase mb-1">Uptime</p>
                        <p class="fs-3 fw-bold text-primary mb-0">{{ $healthStatus['metrics']['uptime']['formatted'] }}</p>
                        <div class="mt-2 d-flex align-items-center justify-content-center gap-2 small text-muted">
                            <i class="fas fa-server"></i>
                            <span>{{ $healthStatus['metrics']['server']['hostname'] }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Load Average (if available) --}}
            @if(isset($healthStatus['metrics']['cpu']['load']))
            <div class="mt-4 row g-3">
                <div class="col-4">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <p class="small text-muted mb-0">Load 1m</p>
                        <p class="fw-bold text-dark mb-0">{{ $healthStatus['metrics']['cpu']['load']['1min'] }}</p>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <p class="small text-muted mb-0">Load 5m</p>
                        <p class="fw-bold text-dark mb-0">{{ $healthStatus['metrics']['cpu']['load']['5min'] }}</p>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <p class="small text-muted mb-0">Load 15m</p>
                        <p class="fw-bold text-dark mb-0">{{ $healthStatus['metrics']['cpu']['load']['15min'] }}</p>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Database Info --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            <h2 class="fw-semibold text-dark mb-4 d-flex align-items-center gap-2" style="font-size:0.9rem;">
                <i class="fas fa-database text-primary"></i> Database Info
            </h2>
            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between py-2 px-3 bg-light rounded-3">
                            <span class="small text-muted">Size</span>
                            <span class="small fw-semibold text-dark">{{ $healthStatus['metrics']['database']['size'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-2 px-3 bg-light rounded-3">
                            <span class="small text-muted">Total Tables</span>
                            <span class="small fw-semibold text-dark">{{ $healthStatus['metrics']['database']['totalTables'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-2 px-3 bg-light rounded-3">
                            <span class="small text-muted">Total Records</span>
                            <span class="small fw-semibold text-dark">{{ $healthStatus['metrics']['database']['totalRecords'] }}</span>
                        </div>
                        <div class="py-2 px-3 bg-light rounded-3">
                            <span class="small text-muted d-block mb-1">Path</span>
                            <span class="small text-secondary break-all">{{ $healthStatus['metrics']['database']['path'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="overflow-auto border rounded-3" style="max-height:240px;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="small fw-semibold">Table</th>
                                    <th class="text-end small fw-semibold">Records</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($healthStatus['metrics']['database']['tables'] ?? [] as $table)
                                <tr>
                                    <td class="small text-secondary">{{ $table['name'] }}</td>
                                    <td class="text-end small fw-medium text-dark">{{ $table['count'] }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Server Info --}}
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body">
            <h2 class="fw-semibold text-dark mb-4 d-flex align-items-center gap-2" style="font-size:0.9rem;">
                <i class="fas fa-server text-primary"></i> Server Info
            </h2>
            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3">
                        <p class="small text-muted text-uppercase mb-0">Hostname</p>
                        <p class="fw-semibold text-dark mt-1 mb-0">{{ $healthStatus['metrics']['server']['hostname'] }}</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3">
                        <p class="small text-muted text-uppercase mb-0">OS</p>
                        <p class="fw-semibold text-dark mt-1 mb-0">{{ $healthStatus['metrics']['server']['os'] }}</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3">
                        <p class="small text-muted text-uppercase mb-0">PHP Version</p>
                        <p class="fw-semibold text-dark mt-1 mb-0">{{ $healthStatus['metrics']['server']['php_version'] }}</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3">
                        <p class="small text-muted text-uppercase mb-0">Laravel</p>
                        <p class="fw-semibold text-dark mt-1 mb-0">v{{ $healthStatus['metrics']['server']['laravel_version'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
