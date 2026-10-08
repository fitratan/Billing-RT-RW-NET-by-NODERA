<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a0a0c">
    <title>Daftar — NODERA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/nodera.css') }}?v=3" rel="stylesheet">
</head>
<body>
    <button class="icon-btn theme-fab" onclick="toggleTheme(event)" aria-label="Tema"><i class="bi bi-moon-stars"></i></button>

    <div class="auth-shell" style="justify-content:flex-start;padding-top:40px">
        <div class="auth-inner" style="max-width:480px">
            <div class="auth-brand">
                <img src="/images/logo.png?v=36" alt="NODERA">
                <span>NODERA</span>
            </div>

            {{-- Invite komunitas --}}
            @php
                $tgCommunity = \App\Models\Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_COMMUNITY_URL')->whereNull('tenant_id')->value('value')
                    ?: \App\Models\Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_COMMUNITY')->whereNull('tenant_id')->value('value')
                    ?: env('TELEGRAM_COMMUNITY_URL', '')
                    ?: 'https://t.me/nodera_community';
                if (!str_starts_with($tgCommunity, 'http')) {
                    $tgCommunity = 'https://t.me/' . ltrim($tgCommunity, '@');
                }
            @endphp
            <a href="{{ $tgCommunity }}" target="_blank" class="card tappable mb-3" style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--cyan-bg);border-color:rgba(45,212,191,.25)">
                <span class="li-ic" style="background:var(--grad);color:#fff"><i class="bi bi-people-fill"></i></span>
                <span class="li-body">
                    <span class="li-title">Gabung Komunitas NODERA</span>
                    <span class="li-sub">Diskusi, update fitur & bantuan sesama pengguna</span>
                </span>
                <i class="bi bi-arrow-right" style="color:var(--brand)"></i>
            </a>

            <div class="auth-card">
                <div class="auth-eyebrow">Mulai berlangganan</div>
                <h1 class="auth-title">Daftar Akun</h1>
                <p class="auth-sub">Kelola ISP Anda dengan platform billing & jaringan</p>

                @if(session('error'))
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> {{ session('error') }}</div>
                @endif
                @if(session('success'))
                <div class="alert alert-success"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
                @endif
                @if($errors->any())
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i>
                    <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
                </div>
                @endif

                <form method="POST" action="/register">
                    @csrf

                    <div class="field">
                        <label class="flabel">Nama Lengkap <span class="req">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="Nama Anda" class="input @error('name') invalid @enderror">
                        @error('name')<span class="err-msg">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label class="flabel">Nama Perusahaan <span class="req">*</span></label>
                        <input type="text" name="company" value="{{ old('company') }}" required placeholder="PT. Digital Network" class="input @error('company') invalid @enderror">
                        @error('company')<span class="err-msg">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label class="flabel">Username Login <span class="req">*</span></label>
                        <input type="text" name="username" value="{{ old('username') }}" required placeholder="adminperusahaan" class="input @error('username') invalid @enderror">
                        @error('username')<span class="err-msg">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label class="flabel">Domain / Subdomain <span class="req">*</span></label>
                        <div style="display:flex;gap:0">
                            <input type="text" name="slug" value="{{ old('slug') }}" required placeholder="perusahaananda"
                                pattern="^[a-z0-9]+(?:-[a-z0-9]+)*$"
                                title="Huruf kecil, angka, dan strip. Tanpa spasi."
                                class="input @error('slug') invalid @enderror"
                                style="border-radius:12px 0 0 12px">
                            <span style="display:flex;align-items:center;padding:0 12px;background:var(--surface2);border:1px solid var(--line2);border-left:none;border-radius:0 12px 12px 0;font-size:12px;color:var(--text3);white-space:nowrap">.dgtlnetsolution.com</span>
                        </div>
                        @error('slug')<span class="err-msg">{{ $message }}</span>@enderror
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label class="flabel">Password <span class="req">*</span></label>
                            <div class="input-with-icon">
                                <i class="bi bi-lock"></i>
                                <input type="password" name="password" id="password" required placeholder="Min 6 karakter" minlength="6" class="input @error('password') invalid @enderror">
                                <button type="button" class="toggle-eye" onclick="togglePwd(this)"><i class="bi bi-eye"></i></button>
                            </div>
                            @error('password')<span class="err-msg">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label class="flabel">Email <span class="req">*</span></label>
                            <input type="email" name="email" value="{{ old('email') }}" required placeholder="admin@perusahaan.com" class="input @error('email') invalid @enderror">
                            @error('email')<span class="err-msg">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="field">
                        <label class="flabel">Nomor HP / WhatsApp <span class="req">*</span></label>
                        <input type="text" name="phone" value="{{ old('phone') }}" required placeholder="08123456789" class="input @error('phone') invalid @enderror">
                        @error('phone')<span class="err-msg">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label class="flabel">Pilih Paket <span class="req">*</span></label>
                        @forelse($packages as $pkg)
                        <label class="sel-card pkg-box" style="margin-bottom:8px">
                            <input type="radio" name="package_id" value="{{ $pkg->id }}" {{ old('package_id')==$pkg->id?'checked':'' }}
                                data-monthly="{{ $pkg->monthly_price ?? 0 }}"
                                data-semi="{{ $pkg->semi_annual_price ?? 0 }}"
                                data-annual="{{ $pkg->annual_price ?? 0 }}"
                                data-durations="{{ $pkg->duration_options ?? '1,6,12' }}" required>
                            <span class="sel-radio"></span>
                            <span class="sel-body">
                                <span class="sel-title">{{ $pkg->name }}</span>
                                <span class="sel-sub">@if($pkg->max_customers > 0) Maks {{ $pkg->max_customers }} pelanggan @else Unlimited @endif</span>
                            </span>
                            <span class="sel-val" style="color:var(--brand)">
                                @if($pkg->monthly_price > 0) Rp{{ number_format($pkg->monthly_price,0,',','.') }} @else Gratis @endif
                                <span style="display:block;font-size:10px;color:var(--text3);font-weight:500">/bulan</span>
                            </span>
                        </label>
                        @empty
                        <div class="muted">Belum ada paket tersedia.</div>
                        @endforelse
                        @error('package_id')<span class="err-msg">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label class="flabel">Durasi Langganan <span class="req">*</span></label>
                        <select name="duration" id="durationSelect" class="select @error('duration') invalid @enderror" required>
                            <option value="1" {{ old('duration')=='1'?'selected':'' }}>1 Bulan</option>
                            <option value="6" {{ old('duration')=='6'?'selected':'' }}>6 Bulan</option>
                            <option value="12" {{ old('duration')=='12'?'selected':'' }}>1 Tahun</option>
                        </select>
                        @error('duration')<span class="err-msg">{{ $message }}</span>@enderror
                        <div class="hint" id="priceDisplay" style="font-size:13px;font-weight:600;color:var(--brand)">Pilih paket & durasi</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg"><i class="bi bi-send"></i> Daftar</button>
                </form>
            </div>

            <p class="auth-foot">Sudah punya akun? <a href="/login">Masuk</a></p>
            <p class="auth-copy"><i class="bi bi-shield-check"></i> NODERA</p>
        </div>
    </div>

    <script>
    (function(){
        var s=localStorage.getItem('n_theme')||'dark';
        document.documentElement.setAttribute('data-theme',s);
        sync(s);
    })();
    function sync(t){var b=document.querySelector('.theme-fab');if(b)b.innerHTML=t==='dark'?'<i class="bi bi-sun"></i>':'<i class="bi bi-moon-stars"></i>';}
    function toggleTheme(e){e.preventDefault();var d=document.documentElement,n=d.getAttribute('data-theme')==='dark'?'light':'dark';d.setAttribute('data-theme',n);localStorage.setItem('n_theme',n);sync(n);}
    function togglePwd(btn){var input=btn.closest('.input-with-icon').querySelector('input');var isPwd=input.type==='password';input.type=isPwd?'text':'password';btn.innerHTML=isPwd?'<i class="bi bi-eye-slash"></i>':'<i class="bi bi-eye"></i>';}

    function bindPkg(){
        document.querySelectorAll('.pkg-box').forEach(function(c){
            var r=c.querySelector('input');
            if(r.checked)c.classList.add('active');
            r.addEventListener('change',function(){
                document.querySelectorAll('.pkg-box').forEach(function(x){x.classList.remove('active')});
                if(r.checked)c.classList.add('active');
                var durations=(r.dataset.durations||'1,6,12').split(',');
                var sel=document.getElementById('durationSelect');
                sel.innerHTML='';
                durations.forEach(function(d){
                    var m=parseInt(d.trim());
                    if(m>0){var label=m===1?'1 Bulan':(m<12?m+' Bulan':'1 Tahun');sel.innerHTML+='<option value="'+m+'">'+label+'</option>';}
                });
                updatePrice();
            });
        });
    }
    function updatePrice(){
        var pkg=document.querySelector('.pkg-box input:checked');
        var dur=document.getElementById('durationSelect');
        var display=document.getElementById('priceDisplay');
        if(!pkg){display.textContent='Pilih paket & durasi';return;}
        var prices={'1':parseInt(pkg.dataset.monthly)||0,'6':parseInt(pkg.dataset.semi)||(parseInt(pkg.dataset.monthly)*6||0),'12':parseInt(pkg.dataset.annual)||(parseInt(pkg.dataset.monthly)*12||0)};
        var price=prices[dur.value]||0;
        var durLabel=dur.options[dur.selectedIndex].text;
        display.textContent=(price>0?'Rp '+price.toLocaleString('id-ID')+' / '+durLabel:'Gratis / '+durLabel);
    }
    bindPkg();
    document.getElementById('durationSelect')?.addEventListener('change',updatePrice);
    updatePrice();
    </script>
</body>
</html>
