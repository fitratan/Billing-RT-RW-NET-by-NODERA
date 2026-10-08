@extends('layouts.admin')

@section('title', 'Pengeluaran')

@section('content')
<div class="container-fluid px-0">
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold text-dark mb-0">Pengeluaran</h1>
            <p class="small text-muted mb-0">Catat dan kelola pengeluaran operasional</p>
        </div>
        <button data-bs-toggle="modal" data-bs-target="#expenseModal" onclick="resetExpenseForm()" class="btn btn-primary btn-sm">
            <i class="bi bi-plus"></i> Catat
        </button>
    </div>

    {{-- Alerts --}}
    

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-3 bg-danger bg-opacity-10 text-danger" style="width:44px;height:44px;flex-shrink:0;">
                        <i class="bi bi-calendar"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Bulan Ini</div>
                        <div class="fs-5 fw-bold text-danger">Rp {{ number_format($totalThisMonth, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-3 bg-primary bg-opacity-10 text-primary" style="width:44px;height:44px;flex-shrink:0;">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Total Tercatat</div>
                        <div class="fs-5 fw-bold text-dark">Rp {{ number_format($totalAll, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            <form method="GET" action="/admin/finance/expenses" class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted">Bulan:</label>
                    <select name="month" class="form-select form-select-sm w-auto">
                        @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ request('month', date('m')) == $m ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted">Tahun:</label>
                    <select name="year" class="form-select form-select-sm w-auto">
                        @for($y = date('Y'); $y >= date('Y')-3; $y--)
                        <option value="{{ $y }}" {{ request('year', date('Y')) == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted">Kategori:</label>
                    <select name="category" class="form-select form-select-sm w-auto">
                        <option value="">Semua</option>
                        @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> Filter
                </button>
            </form>
        </div>
    </div>

    {{-- Expenses Table --}}
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-list me-2 text-primary"></i> Riwayat Pengeluaran</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-semibold text-nowrap">Tanggal</th>
                            <th class="fw-semibold">Kategori</th>
                            <th class="fw-semibold">Deskripsi</th>
                            <th class="fw-semibold text-end">Jumlah</th>
                            <th class="fw-semibold text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expenses as $exp)
                        <tr>
                            <td class="text-nowrap text-secondary">{{ \Carbon\Carbon::parse($exp->date)->format('d/m/Y') }}</td>
                            <td><span class="badge bg-light text-dark fw-normal">{{ $exp->category }}</span></td>
                            <td style="max-width:250px;">
                                <p class="fw-medium text-dark mb-0">{{ $exp->description }}</p>
                                @if($exp->notes)
                                <p class="small text-muted mb-0">{{ Str::limit($exp->notes, 60) }}</p>
                                @endif
                            </td>
                            <td class="text-end fw-semibold text-danger text-nowrap">-Rp{{ number_format($exp->amount, 0, ',', '.') }}</td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary border-0" title="Edit" onclick="openEditExpense({{ $exp->id }})">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" action="/admin/finance/expenses/{{ $exp->id }}/delete"
                                    onsubmit="return confirm('Hapus pengeluaran ini?')" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger border-0" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <p class="mb-0">Belum ada data pengeluaran.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($expenses, 'hasPages') && $expenses->hasPages())
            <div class="mt-3">{{ $expenses->links() }}</div>
            @endif
        </div>
    </div>
</div>

{{-- Add Expense Modal --}}
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="expenseModalLabel"><i class="bi bi-plus-circle me-2 text-primary"></i> Catat Pengeluaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/finance/expenses" id="expenseForm">
                    @csrf
                    <input type="hidden" name="id" id="editExpenseId" value="">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="date" id="editDate" required value="{{ date('Y-m-d') }}" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Kategori <span class="text-danger">*</span></label>
                        <select name="category" id="editCategory" required class="form-select">
                            <option value="">-- Pilih --</option>
                            @foreach(['Operasional', 'Gaji Karyawan', 'Internet & Telekomunikasi', 'Listrik & Air', 'Sewa & Gedung', 'Peralatan', 'Marketing', 'Transportasi', 'Lainnya'] as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Jumlah (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" id="editAmount" required class="form-control" placeholder="Contoh: 150000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Deskripsi <span class="text-danger">*</span></label>
                        <textarea name="description" id="editDescription" required rows="2" class="form-control" placeholder="Deskripsi pengeluaran..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Catatan</label>
                        <textarea name="notes" id="editNotes" rows="2" class="form-control"></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-floppy"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function resetExpenseForm() {
    document.getElementById('expenseModalLabel').innerHTML = '<i class="bi bi-plus-circle me-2 text-primary"></i> Catat Pengeluaran';
    document.getElementById('expenseForm').action = '/admin/finance/expenses';
    document.getElementById('editExpenseId').value = '';
    document.getElementById('editDate').value = '{{ date("Y-m-d") }}';
    document.getElementById('editCategory').value = '';
    document.getElementById('editAmount').value = '';
    document.getElementById('editDescription').value = '';
    document.getElementById('editNotes').value = '';
}

function openEditExpense(id) {
    fetch('/admin/finance/expenses/' + id + '/edit')
        .then(res => res.json())
        .then(resp => {
            if (!resp.success) { alert('Gagal mengambil data'); return; }
            const d = resp.data;
            document.getElementById('expenseModalLabel').innerHTML = '<i class="bi bi-pencil me-2 text-primary"></i> Edit Pengeluaran';
            document.getElementById('expenseForm').action = '/admin/finance/expenses/' + id + '/update';
            document.getElementById('editExpenseId').value = id;
            document.getElementById('editDate').value = d.date;
            document.getElementById('editCategory').value = d.category;
            document.getElementById('editAmount').value = d.amount;
            document.getElementById('editDescription').value = d.description;
            document.getElementById('editNotes').value = d.notes || '';
            new bootstrap.Modal(document.getElementById('expenseModal')).show();
        })
        .catch(err => alert('Gagal terhubung ke server: ' + err.message));
}
</script>
@endpush