<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Aktivasi Lisensi Diperlukan — NODERA Billing</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #07090E; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="min-h-screen text-slate-100 flex items-center justify-center p-4">
    <div class="max-w-md w-full rounded-3xl border border-rose-500/30 bg-[#0B0F19] p-6 sm:p-8 text-center shadow-2xl shadow-rose-950/40 relative overflow-hidden">
        <div class="w-16 h-16 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>

        <h1 class="text-xl font-bold text-white tracking-tight mb-2">Aktivasi Lisensi Diperlukan</h1>
        <p class="text-xs text-slate-400 leading-relaxed mb-5">
            {{ $message ?? 'Lisensi NODERA Billing untuk server ini tidak aktif, kedaluwarsa, terkunci pada server lain, atau telah dinonaktifkan oleh administrator pusat.' }}
        </p>

        @if(!empty($license_key))
        <div class="rounded-xl border border-slate-800 bg-slate-950/80 p-3 mb-4 text-left">
            <span class="text-[10px] text-slate-500 uppercase tracking-wider font-bold block mb-1">License Key Terpasang</span>
            <div class="font-mono text-xs text-slate-300 font-bold break-all select-all">{{ $license_key }}</div>
        </div>
        @endif

        <!-- Form Input License Baru -->
        <form id="licenseForm" class="space-y-3 mb-5 text-left bg-slate-950/60 p-3.5 rounded-2xl border border-slate-800">
            <label class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Masukkan License Key Baru</label>
            <input type="text" id="new_license_key" placeholder="NDR-ISP-XXXX-XXXX-XXXX" required class="w-full font-mono text-xs px-3 py-2.5 rounded-xl bg-[#121720] border border-slate-700 text-white uppercase placeholder:text-slate-600 focus:outline-none focus:border-cyan-400">
            <button type="submit" id="submitBtn" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-cyan-500 hover:brightness-110 text-slate-950 font-extrabold text-xs shadow-lg transition-all">
                Aktivasi Lisensi Baru
            </button>
            <div id="statusMsg" class="text-[11px] text-center hidden"></div>
        </form>

        <div class="space-y-2.5">
            <a href="https://panel.dgtlnetsolution.com/desktop-licenses" target="_blank" class="w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-xl border border-slate-800 bg-slate-900/60 text-slate-300 text-xs font-semibold hover:bg-slate-800 transition-colors">
                Beli / Kelola Lisensi di Portal Pusat
            </a>
            <a href="https://wa.me/6285155173547?text=Halo%20Admin%20NODERA,%20saya%20butuh%20bantuan%20aktivasi%20lisensi%20ISP" target="_blank" class="w-full inline-flex items-center justify-center py-2.5 rounded-xl border border-slate-800 bg-slate-900/60 text-slate-300 text-xs font-semibold hover:bg-slate-800 transition-colors">
                Hubungi Dukungan Teknis (WhatsApp)
            </a>
        </div>

        <p class="text-[10px] text-slate-600 mt-6 font-mono">NODERA Enterprise Billing · Guard of Distribution</p>
    </div>

    <script>
        document.getElementById('licenseForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const key = document.getElementById('new_license_key').value.trim();
            const btn = document.getElementById('submitBtn');
            const msg = document.getElementById('statusMsg');
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            if (!key) return;

            btn.disabled = true;
            btn.innerText = 'Memverifikasi...';
            msg.className = 'text-[11px] text-center block text-slate-400';
            msg.innerText = 'Menghubungi server pusat lisensi...';

            try {
                const res = await fetch('/admin/my-settings/save-license', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ license_key: key })
                });

                const data = await res.json();
                if (data.success) {
                    msg.className = 'text-[11px] text-center block text-emerald-400 font-bold';
                    msg.innerText = data.message || 'Lisensi berhasil diaktivasi! Memuat ulang...';
                    setTimeout(() => { window.location.href = '/dashboard'; }, 1200);
                } else {
                    btn.disabled = false;
                    btn.innerText = 'Aktivasi Lisensi Baru';
                    msg.className = 'text-[11px] text-center block text-rose-400';
                    msg.innerText = data.message || 'Lisensi tidak valid atau ditolak oleh server.';
                }
            } catch (err) {
                btn.disabled = false;
                btn.innerText = 'Aktivasi Lisensi Baru';
                msg.className = 'text-[11px] text-center block text-rose-400';
                msg.innerText = 'Terjadi kesalahan jaringan saat menghubungi server.';
            }
        });
    </script>
</body>
</html>
