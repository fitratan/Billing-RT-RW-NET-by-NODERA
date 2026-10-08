<!-- PWA Smart Install Banner Component -->
<div id="pwaInstallBanner" class="hidden my-4">
    <div class="bg-gradient-to-r from-emerald-500/10 via-emerald-500/5 to-amber-500/10 border border-emerald-500/25 dark:border-emerald-500/30 rounded-2xl p-4 flex items-center justify-between gap-3 shadow-sm">
        <div class="flex items-center space-x-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-sm shadow-emerald-600/20">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs font-bold text-slate-900 dark:text-white truncate">Pasang Aplikasi di Layar Utama</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">Akses cepat dan mudah seperti aplikasi HP</p>
            </div>
        </div>

        <div class="flex items-center space-x-1.5 shrink-0">
            <button id="pwaInstallTriggerBtn" onclick="pwaInstallAction()" type="button"
                class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>Pasang</span>
            </button>
            <button onclick="dismissPwaBanner()" type="button"
                title="Tutup banner"
                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-200/50 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
</div>

<!-- iOS Safari PWA Installation Guidance Modal -->
<div id="pwaIosModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl relative text-left">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-4">
            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pasang di Perangkat iOS / Safari</h3>
            </div>
            <button onclick="closeIosModal()" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
            Ikuti 3 langkah mudah berikut untuk menambahkan aplikasi ini ke layar utama iPhone/iPad Anda:
        </p>

        <ol class="space-y-3 text-xs text-slate-700 dark:text-slate-300">
            <li class="flex items-start space-x-2.5">
                <span class="w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 font-bold flex items-center justify-center shrink-0 text-[11px]">1</span>
                <div>
                    Ketuk ikon <strong>Bagikan (Share)</strong> di bilah menu bawah browser Safari.
                </div>
            </li>
            <li class="flex items-start space-x-2.5">
                <span class="w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 font-bold flex items-center justify-center shrink-0 text-[11px]">2</span>
                <div>
                    Gulir ke bawah dan pilih <strong>Tambah ke Layar Utama (Add to Home Screen)</strong>.
                </div>
            </li>
            <li class="flex items-start space-x-2.5">
                <span class="w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 font-bold flex items-center justify-center shrink-0 text-[11px]">3</span>
                <div>
                    Ketuk <strong>Tambah</strong> di sudut kanan atas untuk menyelesaikan pemasangan.
                </div>
            </li>
        </ol>

        <div class="mt-6">
            <button onclick="closeIosModal()" type="button"
                class="w-full py-2.5 px-4 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors">
                Saya Mengerti
            </button>
        </div>
    </div>
</div>

<script>
    (function () {
        let pwaDeferredPrompt = null;
        const banner = document.getElementById('pwaInstallBanner');
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        const isIos = /iPhone|iPad|iPod/.test(navigator.userAgent) && !window.MSStream;

        if (isStandalone) {
            if (banner) banner.classList.add('hidden');
            return;
        }

        const isDismissed = sessionStorage.getItem('pwa_banner_dismissed') === 'true';

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            pwaDeferredPrompt = e;
            if (banner && !isDismissed) {
                banner.classList.remove('hidden');
            }
        });

        // If iOS Safari and not standalone, show banner unless dismissed
        if (isIos && !isStandalone && banner && !isDismissed) {
            banner.classList.remove('hidden');
        }

        window.pwaInstallAction = function () {
            if (pwaDeferredPrompt) {
                pwaDeferredPrompt.prompt();
                pwaDeferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        if (banner) banner.classList.add('hidden');
                    }
                    pwaDeferredPrompt = null;
                });
            } else if (isIos) {
                const modal = document.getElementById('pwaIosModal');
                if (modal) modal.classList.remove('hidden');
            } else {
                alert('Untuk memasang aplikasi: Gunakan menu browser Anda (titik tiga) lalu pilih "Tambahkan ke Layar Utama" / "Install App".');
            }
        };

        window.closeIosModal = function () {
            const modal = document.getElementById('pwaIosModal');
            if (modal) modal.classList.add('hidden');
        };

        window.dismissPwaBanner = function () {
            if (banner) banner.classList.add('hidden');
            sessionStorage.setItem('pwa_banner_dismissed', 'true');
        };
    })();
</script>
