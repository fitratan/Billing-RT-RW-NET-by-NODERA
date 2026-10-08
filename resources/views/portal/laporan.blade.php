@extends('portal.layout')

@section('title', 'Laporan - Portal')

@section('content')
<div class="max-w-lg mx-auto min-h-screen pb-28 px-4 pt-5">
    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="/portal" class="w-9 h-9 rounded-xl bg-white shadow-sm border border-gray-100 flex items-center justify-center text-gray-400">
            <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-lg font-bold text-gray-900">Laporan Gangguan</h1>
            <p class="text-xs text-gray-400">Kirim keluhan Anda ke admin</p>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 mb-4 flex items-start gap-3">
        <i class="fas fa-check-circle text-emerald-500 mt-0.5"></i>
        <div>
            <p class="text-sm font-semibold text-emerald-800">Berhasil!</p>
            <p class="text-xs text-emerald-600">{{ session('success') }}</p>
        </div>
    </div>
    @endif

    {{-- Form --}}
    <div class="card mb-6">
        <form method="POST" action="/portal/laporan" enctype="multipart/form-data">
            @csrf
            <div class="mb-4">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">Judul Keluhan <span class="text-red-500">*</span></label>
                <input type="text" name="title" required minlength="5" maxlength="200"
                    placeholder="Contoh: Internet putus sejak pagi"
                    class="w-full px-4 py-3 rounded-2xl border border-gray-200 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none transition">
            </div>
            <div class="mb-4">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">Deskripsi <span class="text-red-500">*</span></label>
                <textarea name="description" required minlength="10" rows="4"
                    placeholder="Jelaskan keluhan Anda secara detail..."
                    class="w-full px-4 py-3 rounded-2xl border border-gray-200 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none transition resize-none"></textarea>
            </div>
            <div class="mb-4">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">Lampiran (opsional)</label>
                <div class="border-2 border-dashed border-gray-200 rounded-2xl p-4 text-center cursor-pointer hover:border-blue-400 transition" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-cloud-upload-alt text-2xl text-gray-300 mb-2"></i>
                    <p class="text-xs text-gray-400" id="fileName">Tap untuk upload foto/video</p>
                    <input type="file" name="attachment" id="fileInput" accept="image/*,video/*" class="hidden" onchange="document.getElementById('fileName').textContent = this.files[0]?.name || 'Tap untuk upload foto/video'">
                </div>
                <p class="text-[11px] text-gray-400 mt-1">Max 20MB. Format: JPG, PNG, MP4</p>
            </div>
            <button type="submit" class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-blue-600 to-blue-700 text-white font-bold text-sm shadow-lg shadow-blue-200 hover:shadow-xl transition flex items-center justify-center gap-2">
                <i class="fas fa-paper-plane"></i> Kirim Laporan
            </button>
        </form>
    </div>

    {{-- Riwayat Tiket --}}
    @if($tickets->count() > 0)
    <div class="card">
        <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <i class="fas fa-history text-blue-500 text-sm"></i> Riwayat Laporan
        </h2>
        <div class="divide-y divide-gray-50">
            @foreach($tickets as $t)
            @php
                $statusBadge = match($t->status) {
                    'pending' => ['bg-yellow-100 text-yellow-700', 'Menunggu'],
                    'open' => ['bg-blue-100 text-blue-700', 'Diproses Admin'],
                    'assigned' => ['bg-indigo-100 text-indigo-700', 'Diteruskan ke Teknisi'],
                    'in_progress' => ['bg-cyan-100 text-cyan-700', 'Dikerjakan'],
                    'resolved' => ['bg-green-100 text-green-700', 'Selesai'],
                    'closed' => ['bg-gray-100 text-gray-500', 'Ditutup'],
                    default => ['bg-gray-100 text-gray-500', $t->status],
                };
            @endphp
            <div class="py-3">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-sm font-medium text-gray-900 truncate max-w-[70%]">{{ $t->title ?? 'Laporan #'.$t->id }}</p>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium {{ $statusBadge[0] }}">
                        {{ $statusBadge[1] }}
                    </span>
                </div>
                <p class="text-xs text-gray-400">{{ $t->created_at->diffForHumans() }}</p>
                @if($t->resolved_by && $t->resolvedBy)
                <p class="text-[11px] text-gray-500 mt-1">
                    <i class="fas fa-check-circle text-green-500 mr-1"></i> Diselesaikan oleh: {{ $t->resolvedBy->name }}
                </p>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
