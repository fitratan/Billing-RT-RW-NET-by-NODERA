@extends('layouts.admin')
@section('title', 'Top Bandwidth')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">
            <i class="bi bi-speedometer2 me-2"></i>Top Bandwidth
        </h1>
    </div>

    {{-- Router selector --}}
    <div class="card shadow-sm rounded-3 border-0 mb-3">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-2">
                <label class="small text-muted fw-medium">Router:</label>
                <select id="routerSelect" class="form-select form-select-sm w-auto">
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Tipe selector + Refresh --}}
    <div class="card shadow-sm rounded-3 border-0 mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3">
                <span class="fw-medium text-secondary small">Pilih Tipe:</span>
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="type" id="typePppoe" value="pppoe" checked autocomplete="off">
                    <label class="btn btn-outline-primary rounded-start-3" for="typePppoe">
                        <i class="bi bi-hdd-network me-1"></i> PPPoE
                    </label>

                    <input type="radio" class="btn-check" name="type" id="typeHotspot" value="hotspot" autocomplete="off">
                    <label class="btn btn-outline-primary" for="typeHotspot">
                        <i class="bi bi-wifi me-1"></i> Hotspot
                    </label>

                    <input type="radio" class="btn-check" name="type" id="typeArp" value="arp" autocomplete="off">
                    <label class="btn btn-outline-primary rounded-end-3" for="typeArp">
                        <i class="bi bi-diagram-3 me-1"></i> ARP / Static
                    </label>
                </div>
                <button class="btn btn-primary rounded-3 ms-auto" id="btnRefresh" onclick="loadData()">
                    <i class="bi bi-arrow-clockwise"></i> Muat Ulang
                </button>
            </div>
        </div>
    </div>

    {{-- Loading indicator --}}
    <div id="loadingIndicator" class="text-center py-5 d-none">
        <div class="spinner-border text-primary mb-3" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="text-muted small">Memuat data...</p>
    </div>

    {{-- Error alert --}}
    <div id="errorAlert" class="d-none"></div>

    {{-- Hotspot not setup alert --}}
    <div id="hotspotNotSetup" class="d-none">
        <div class="alert alert-info d-flex align-items-center gap-2 shadow-sm rounded-3 mb-4 py-3" role="alert">
            <i class="bi bi-info-circle text-info fs-5"></i>
            <span>Hotspot belum di setup</span>
        </div>
    </div>

    {{-- Empty data --}}
    <div id="emptyData" class="d-none">
        <div class="card shadow-sm rounded-3 border-0">
            <div class="card-body text-center py-5">
                <i class="bi bi-inbox text-secondary" style="font-size: 3rem;"></i>
                <p class="text-muted small mt-3 mb-0">Tidak ada data aktif</p>
            </div>
        </div>
    </div>

    {{-- Tabel hasil --}}
    <div id="resultTable" class="d-none">
        <div class="card shadow-sm rounded-3 border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="min-width: 500px;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4" style="width: 40px;">No</th>
                                <th style="min-width: 100px;">Username / Nama</th>
                                <th class="d-none d-md-table-cell">IP Address</th>
                                <th class="d-none d-sm-table-cell">Uptime</th>
                                <th class="text-end" style="width: 70px;">TX</th>
                                <th class="text-end" style="width: 70px;">RX</th>
                                <th class="text-end pe-4" style="width: 80px;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function loadData() {
    const type = document.querySelector('input[name="type"]:checked')?.value || 'pppoe';
    const routerId = document.getElementById('routerSelect')?.value;

    if (!routerId) {
        const alert = document.getElementById('errorAlert');
        alert.className = 'alert alert-warning d-flex align-items-center gap-2 shadow-sm rounded-3 mb-4 py-3';
        alert.innerHTML = '<i class="bi bi-exclamation-triangle text-warning"></i><span>Silakan pilih router terlebih dahulu</span>';
        alert.classList.remove('d-none');
        return;
    }

    // Show loading, hide others
    document.getElementById('loadingIndicator').classList.remove('d-none');
    document.getElementById('errorAlert').classList.add('d-none');
    document.getElementById('hotspotNotSetup').classList.add('d-none');
    document.getElementById('emptyData').classList.add('d-none');
    document.getElementById('resultTable').classList.add('d-none');

    try {
        const url = new URL('{{ url("/admin/top-bandwidth/data") }}', window.location.origin);
        url.searchParams.set('type', type);
        url.searchParams.set('router_id', routerId);

        const res = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }

        const json = await res.json();

        document.getElementById('loadingIndicator').classList.add('d-none');

        if (!json.success) {
            if (json.hotspot_not_setup) {
                document.getElementById('hotspotNotSetup').classList.remove('d-none');
            } else {
                const alert = document.getElementById('errorAlert');
                alert.className = 'alert alert-warning d-flex align-items-center gap-2 shadow-sm rounded-3 mb-4 py-3';
                alert.innerHTML = '<i class="bi bi-exclamation-triangle text-warning"></i><span>' + (json.message || 'Terjadi kesalahan') + '</span>';
                alert.classList.remove('d-none');
            }
            return;
        }

        if (!json.data || json.data.length === 0) {
            document.getElementById('emptyData').classList.remove('d-none');
            return;
        }

        // Render table
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';

        json.data.forEach((row, i) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="ps-4 fw-medium text-secondary">${i + 1}</td>
                <td class="fw-medium">${escapeHtml(row.name)}</td>
                <td class="d-none d-md-table-cell"><code class="small">${escapeHtml(row.address)}</code></td>
                <td class="small text-muted d-none d-sm-table-cell">${escapeHtml(row.uptime)}</td>
                <td class="text-end">${row.tx}</td>
                <td class="text-end">${row.rx}</td>
                <td class="text-end pe-4 fw-semibold">${row.total}</td>
            `;
            tbody.appendChild(tr);
        });

        // Grand total — render as footer row matching visible columns
        const footerRow = document.createElement('tr');
        footerRow.className = 'table-secondary fw-semibold';
        footerRow.innerHTML =
            '<td class="ps-4"></td>' +
            '<td class="small" colspan="4">Grand Total</td>' +
            '<td class="pe-4 text-end" colspan="2">' + (json.grand_total || '') + '</td>';
        tbody.appendChild(footerRow);

        document.getElementById('resultTable').classList.remove('d-none');
    } catch (err) {
        document.getElementById('loadingIndicator').classList.add('d-none');

        const alert = document.getElementById('errorAlert');
        alert.className = 'alert alert-danger d-flex align-items-center gap-2 shadow-sm rounded-3 mb-4 py-3';
        alert.innerHTML = '<i class="bi bi-exclamation-diamond text-danger"></i><span>Gagal memuat data: ' + err.message + '</span>';
        alert.classList.remove('d-none');
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load data on page load with first router
document.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('routerSelect');
    if (sel && sel.options.length > 0) {
        loadData();
    }

    // Re-load when type changes
    document.querySelectorAll('input[name="type"]').forEach(function (el) {
        el.addEventListener('change', function () {
            loadData();
        });
    });

    // Re-load when router changes
    if (sel) {
        sel.addEventListener('change', function () {
            loadData();
        });
    }
});
</script>
@endpush
