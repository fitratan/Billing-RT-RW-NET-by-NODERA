@extends('portal.layout')

@section('title', 'Pengaturan WiFi')

@section('content')
<div class="max-w-lg mx-auto min-h-screen pb-8 px-4 pt-5">
    <div class="flex items-center gap-3 mb-6">
        <a href="/portal" class="w-10 h-10 rounded-2xl bg-white shadow-sm border border-gray-100 flex items-center justify-center text-gray-500">
            <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <h1 class="text-xl font-bold text-gray-900">Pengaturan WiFi</h1>
    </div>

    <div class="card mb-4">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center">
                <i class="fas fa-wifi text-blue-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900">{{ $customer->name }}</p>
                <p class="text-xs text-gray-400">{{ $customer->pppoe_username }}</p>
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">SSID (Nama WiFi)</label>
                <div class="flex gap-2">
                    <input type="text" id="ssidInput" placeholder="Masukkan SSID baru" class="flex-1 px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 text-sm">
                    <button onclick="updateSsid()" class="btn-primary px-5 rounded-2xl text-white text-sm font-semibold">Simpan</button>
                </div>
                <p id="ssidStatus" class="text-xs mt-1"></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Password WiFi</label>
                <div class="flex gap-2">
                    <input type="text" id="passInput" placeholder="Min 8 karakter" class="flex-1 px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 text-sm">
                    <button onclick="updatePassword()" class="btn-primary px-5 rounded-2xl text-white text-sm font-semibold">Simpan</button>
                </div>
                <p id="passStatus" class="text-xs mt-1"></p>
            </div>

            <div class="pt-4 border-t border-gray-100">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Password Login Portal</label>
                <div class="flex gap-2">
                    <input type="password" id="portalPassInput" placeholder="Password baru" class="flex-1 px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 text-sm">
                    <button onclick="changePortalPassword()" class="px-5 py-3 rounded-2xl bg-gray-900 text-white text-sm font-semibold">Ganti</button>
                </div>
                <p id="portalPassStatus" class="text-xs mt-1"></p>
            </div>
        </div>
    </div>
</div>

<script>
    function updateSsid() {
        const ssid = document.getElementById('ssidInput').value;
        if (!ssid || ssid.length < 3) return alert('SSID minimal 3 karakter');
        const btn = event.target;
        btn.disabled = true; btn.textContent = '...';

        fetch('/portal/updateSsid', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: 'ssid=' + encodeURIComponent(ssid)
        })
        .then(r => r.json())
        .then(d => {
            document.getElementById('ssidStatus').textContent = d.message;
            document.getElementById('ssidStatus').className = 'text-xs mt-1 ' + (d.success ? 'text-green-600' : 'text-red-600');
        })
        .catch(e => alert('Error: ' + e.message))
        .finally(() => { btn.disabled = false; btn.textContent = 'Simpan'; });
    }

    function updatePassword() {
        const pass = document.getElementById('passInput').value;
        if (!pass || pass.length < 8) return alert('Password minimal 8 karakter');
        const btn = event.target;
        btn.disabled = true; btn.textContent = '...';

        fetch('/portal/updatePassword', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: 'password=' + encodeURIComponent(pass)
        })
        .then(r => r.json())
        .then(d => {
            document.getElementById('passStatus').textContent = d.message;
            document.getElementById('passStatus').className = 'text-xs mt-1 ' + (d.success ? 'text-green-600' : 'text-red-600');
        })
        .catch(e => alert('Error: ' + e.message))
        .finally(() => { btn.disabled = false; btn.textContent = 'Simpan'; });
    }

    function changePortalPassword() {
        const pass = document.getElementById('portalPassInput').value;
        if (!pass) return alert('Password tidak boleh kosong');

        fetch('/portal/changePortalPassword', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: 'portal_password=' + encodeURIComponent(pass)
        })
        .then(r => r.json())
        .then(d => {
            document.getElementById('portalPassStatus').textContent = d.message;
            document.getElementById('portalPassStatus').className = 'text-xs mt-1 ' + (d.success ? 'text-green-600' : 'text-red-600');
        })
        .catch(e => alert('Error: ' + e.message));
    }
</script>
@endsection
