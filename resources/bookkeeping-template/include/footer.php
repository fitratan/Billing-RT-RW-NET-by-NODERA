    </div><!-- /page-content -->

</div><!-- /page-shell -->

<!-- FAB Button (mobile only, rendered dynamically) -->
<div id="fabWrapper"></div>

<script>
function toggleFab() {
    var acts = document.getElementById('fabActions');
    var icon = document.getElementById('fabIcon');
    var overlay = document.getElementById('fabOverlay');
    if (!acts) return;
    var isOpen = acts.style.display !== 'none';
    if (!isOpen) {
        acts.style.display = 'flex';
        acts.classList.add('fab-open');
        if (overlay) overlay.style.display = 'block';
        if (icon) icon.style.transform = 'rotate(45deg)';
    } else {
        acts.style.display = 'none';
        acts.classList.remove('fab-open');
        if (overlay) overlay.style.display = 'none';
        if (icon) icon.style.transform = 'rotate(0deg)';
    }
}

function syncFAB() {
    var fabDataEl = document.getElementById('pageFabData');
    var container = document.getElementById('fabWrapper');
    if (!container) return;
    if (!fabDataEl) { container.innerHTML = ''; return; }

    try {
        var pageFab = JSON.parse(fabDataEl.textContent);
        if (!pageFab || (Array.isArray(pageFab) && pageFab.length === 0) || (typeof pageFab === 'object' && Object.keys(pageFab).length === 0)) {
            container.innerHTML = '';
            return;
        }

        if (pageFab.icon) {
            var singleClick = pageFab.onclick || (pageFab.target ? "openModal('" + pageFab.target + "')" : '');
            container.innerHTML = '<button class="fab-btn" onclick="' + singleClick + '" aria-label="' + (pageFab.label||'') + '"><i class="bi ' + pageFab.icon + '"></i></button>';
        } else if (Array.isArray(pageFab) && pageFab.length > 0) {
            var actsHtml = pageFab.map(function(act) {
                var actClick = act.onclick || (act.target ? (act.target.includes('(') ? act.target : "openModal('" + act.target + "')") : '');
                var color = act.color || 'var(--primary)';
                return '<div style="display:flex;align-items:center;gap:12px;">' +
                    '<span style="background:var(--card);color:var(--card-fg);padding:6px 12px;border-radius:8px;font-size:12px;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,.15);">' + act.label + '</span>' +
                    '<button onclick="toggleFab(); ' + actClick + '" style="width:48px;height:48px;border-radius:50%;background:var(--card);border:1px solid var(--border);color:' + color + ';display:flex;align-items:center;justify-content:center;font-size:20px;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,.15);">' +
                        '<i class="bi ' + act.icon + '"></i>' +
                    '</button>' +
                '</div>';
            }).join('');

            container.innerHTML = '<div class="fab-actions" id="fabActions" style="display:none;position:fixed;bottom:calc(64px + env(safe-area-inset-bottom) + 80px);right:16px;z-index:49;flex-direction:column;gap:12px;align-items:flex-end;">' + actsHtml + '</div>' +
                '<button class="fab-btn" id="fabBtn" onclick="toggleFab()" aria-label="Menu Aksi"><i class="bi bi-plus-lg" id="fabIcon" style="transition:transform .2s;"></i></button>' +
                '<div id="fabOverlay" style="display:none;position:fixed;top:0;right:0;bottom:0;left:0;background:rgba(0,0,0,.4);z-index:48;backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);" onclick="toggleFab()"></div>';
        }
    } catch(e) {
        container.innerHTML = '';
    }
}
syncFAB();
</script>

<!-- ══════════════════════════════════════════ -->
<!--  MobileBottomNavigation                    -->
<!-- ══════════════════════════════════════════ -->
<nav class="bottom-nav">
    <div class="bottom-nav-inner">
        <?php foreach ($navItems as $item):
            $isActive = $activeTab === $item['page'];
        ?>
            <a href="index.php?page=<?= $item['page'] ?>" class="<?= $isActive ? 'active' : '' ?>">
                <i class="bi <?= $item['icon'] ?>" style="<?= $isActive ? 'transform:translateY(-1px) scale(1.1);color:var(--primary);' : '' ?>"></i>
                <span style="<?= $isActive ? 'font-weight:600;color:var(--primary);' : '' ?>"><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</nav>

<!-- ══════════════════════════════════════════ -->
<!--  Toast "Data disegarkan"                   -->
<!-- ══════════════════════════════════════════ -->
<div id="reloadToast" style="display:none;position:fixed;left:50%;top:12px;z-index:70;transform:translateX(-50%);
    background:color-mix(in srgb,var(--bg) 95%,transparent);
    border:1px solid rgba(34,197,94,.3);color:#22c55e;
    border-radius:999px;padding:8px 16px;font-size:12px;font-weight:500;
    box-shadow:0 4px 16px rgba(0,0,0,.15);backdrop-filter:blur(8px);
    opacity:0;transition:opacity .25s;">
    <span style="display:flex;align-items:center;gap:6px;"><i class="bi bi-check-circle-fill"></i> Data disegarkan</span>
</div>

<script>
// ── Theme toggle (same as ThemeToggle component) ──
function _syncThemeIcons(){
    var dark = document.documentElement.classList.contains('dark');
    ['themeIcon','themeIconDesk'].forEach(function(id){
        var el=document.getElementById(id);
        if(el) el.className = dark ? 'bi bi-moon-stars-fill' : 'bi bi-sun';
    });
}
_syncThemeIcons();
function toggleTheme(){
    var dark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('bk_theme', dark ? 'dark' : 'light');
    _syncThemeIcons();
}

// ── Reload (same as handleReload + toast) ──
function triggerReload(){
    ['reloadIcon','reloadIconDesk'].forEach(function(id){
        var el=document.getElementById(id);
        if(el){ el.style.animation='none'; el.offsetHeight; el.style.animation='spin-once .6s ease'; }
    });
    setTimeout(function(){ window.location.reload(); }, 350);
}
// Show toast on load if came from reload
(function(){
    if(sessionStorage.getItem('bk_reload')){
        sessionStorage.removeItem('bk_reload');
        var t=document.getElementById('reloadToast');
        t.style.display='block';
        setTimeout(function(){ t.style.opacity='1'; },10);
        setTimeout(function(){ t.style.opacity='0'; setTimeout(function(){ t.style.display='none'; },300); },1600);
    }
})();

// ── Modal ──
function openModal(id){
    var el = document.getElementById(id);
    if (el) {
        if (el.parentNode !== document.body) document.body.appendChild(el);
        el.classList.add('open');
    }
}
function closeModal(id){
    if (id) {
        var el = document.getElementById(id);
        if (el) el.classList.remove('open');
    }
    document.querySelectorAll('.modal-backdrop.open').forEach(function(m) {
        m.classList.remove('open');
    });
}
// Close modal on backdrop click
document.addEventListener('click',function(e){
    if(e.target.classList.contains('modal-backdrop')) {
        e.target.classList.remove('open');
        closeModal();
    }
});
// Close flash banners
document.querySelectorAll('.flash-close').forEach(function(b){
    b.addEventListener('click',function(){ b.closest('.flash').remove(); });
});

// ── Export Excel ──
function exportExcel(){
    var rawData = <?= json_encode(getBkData()) ?>;
    var txs = rawData.transactions||[];
    if(!txs.length){ alert('Belum ada transaksi.'); return; }
    var csv='ID,Jenis,Kategori,Nominal,Tanggal,Keterangan\n';
    txs.forEach(function(t){
        csv+=[t.id, t.type==='income'?'Pemasukan':'Pengeluaran',
              '"'+(t.category||'').replace(/"/g,'""')+'"',
              t.amount, t.transaction_date,
              '"'+(t.description||'').replace(/"/g,'""')+'"'].join(',')+ '\n';
    });
    var blob=new Blob(['\ufeff'+csv],{type:'text/csv;charset=utf-8;'});
    var a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download='laporan-<?= htmlspecialchars($subdomain) ?>-'+new Date().toISOString().slice(0,10)+'.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
}

// ── Service Worker & PWA Install ──
let deferredPrompt = null;
if('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js').catch(function(){});
}
window.addEventListener('beforeinstallprompt', function(e) {
    e.preventDefault();
    deferredPrompt = e;
    var pwaBtn = document.getElementById('pwaInstallBtn');
    if(pwaBtn) pwaBtn.style.display = 'flex';
});

function installPWA() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function(result) {
            if (result.outcome === 'accepted') {
                var pwaBtn = document.getElementById('pwaInstallBtn');
                if (pwaBtn) pwaBtn.style.display = 'none';
            }
            deferredPrompt = null;
        });
    } else {
        alert('Untuk menginstal aplikasi Pembukuan ke HP:\n• Android / Chrome: Ketuk menu titik tiga (⋮) > pilih "Instal aplikasi" atau "Tambahkan ke Layar Utama"\n• iPhone / Safari: Ketuk tombol Bagikan (Share) > pilih "Tambah ke Layar Utama"');
    }
}

// ── SPA Router: In-memory cache + hover prefetch (instant repeat nav) ────
(function(){
    var _cache = {};
    var _inFlight = {};

    window.clearSPACache = function() {
        _cache = {};
    };

    document.addEventListener('submit', function() {
        window.clearSPACache();
    });

    function fetchPage(url) {
        if (_cache[url]) return Promise.resolve(_cache[url]);
        if (_inFlight[url]) return _inFlight[url];
        var p = fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(res) { return res.text(); })
            .then(function(html) {
                var parser  = new DOMParser();
                var doc     = parser.parseFromString(html, 'text/html');
                var content = doc.querySelector('.page-content');
                var title   = doc.querySelector('title');
                var entry   = { html: content ? content.innerHTML : null, title: title ? title.innerText : '' };
                _cache[url] = entry;
                delete _inFlight[url];
                return entry;
            });
        _inFlight[url] = p;
        return p;
    }

    function loadPageSPA(url, pushState) {
        if (pushState === undefined) pushState = true;
        var pageShell = document.querySelector('.page-content');
        if (!pageShell) return;

        // Close any open modals / FAB overlays on page transition (preserve modal DOM elements)
        document.querySelectorAll('.modal-backdrop.open').forEach(function(m) {
            m.classList.remove('open');
        });
        var fabActs = document.getElementById('fabActions');
        var fabOvl  = document.getElementById('fabOverlay');
        var fabIcon = document.getElementById('fabIcon');
        if (fabActs) fabActs.style.display = 'none';
        if (fabOvl)  fabOvl.style.display = 'none';
        if (fabIcon) fabIcon.style.transform = 'rotate(0deg)';

        var activePage = new URL(url, window.location.origin).searchParams.get('page') || 'dashboard';
        updateActiveNav(activePage);

        var isCached = !!_cache[url];
        if (!isCached) {
            pageShell.style.opacity = '0.7';
        }

        fetchPage(url).then(function(entry) {
            if (!entry.html) { window.location.href = url; return; }
            if (entry.title) document.title = entry.title;
            pageShell.innerHTML = entry.html;

            pageShell.querySelectorAll('script').forEach(function(s) {
                var ns = document.createElement('script');
                if (s.src) ns.src = s.src; else ns.textContent = s.textContent;
                document.body.appendChild(ns).parentNode.removeChild(ns);
            });

            syncFAB();
            if (pushState) history.pushState({ page: activePage }, '', url);
            window.scrollTo({ top: 0, behavior: 'instant' });
            pageShell.style.opacity = '1';
        }).catch(function() { window.location.href = url; });
    }

    function updateActiveNav(page) {
        document.querySelectorAll('.bottom-nav a').forEach(function(a) {
            var isAct = a.href.includes('page=' + page) || (page === 'dashboard' && a.href.includes('page=dashboard'));
            a.classList.toggle('active', isAct);
            var icon = a.querySelector('i');
            if (icon) icon.style.cssText = isAct ? 'transform:translateY(-1px) scale(1.1);color:var(--primary);' : '';
            var span = a.querySelector('span');
            if (span) span.style.fontWeight = isAct ? '600' : '400';
        });

        document.querySelectorAll('.sidebar-nav a').forEach(function(a) {
            var isAct = a.href.includes('page=' + page) || (page === 'dashboard' && a.href.includes('page=dashboard'));
            a.classList.toggle('active', isAct);
        });
    }

    // Background pre-fetch all main routes after initial load
    setTimeout(function() {
        ['dashboard', 'transactions', 'chat', 'reports'].forEach(function(p) {
            fetchPage('index.php?page=' + p);
        });
    }, 200);

    // Hover prefetch: start fetching on mouseenter so click is instant
    var _hoverTimer = null;
    document.addEventListener('mouseover', function(e) {
        var a = e.target.closest('a');
        if (!a) return;
        var href = a.getAttribute('href');
        if (!href || !href.includes('index.php') || href.includes('logout') || href.includes('delete')) return;
        clearTimeout(_hoverTimer);
        _hoverTimer = setTimeout(function() { fetchPage(href); }, 40);
    });
    document.addEventListener('mouseout', function() { clearTimeout(_hoverTimer); });

    document.addEventListener('click', function(e) {
        var a = e.target.closest('a');
        if (!a) return;
        var href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.includes('logout.php') || href.includes('action=export') || href.includes('delete') || a.target === '_blank' || e.ctrlKey || e.metaKey) {
            if (href && href.includes('delete')) {
                window.clearSPACache();
            }
            return;
        }

        if (href.includes('index.php')) {
            e.preventDefault();
            loadPageSPA(href);
        }
    });

    window.addEventListener('popstate', function() {
        loadPageSPA(window.location.href, false);
    });
})();
</script>
<!-- Shared Modals -->
<?php require_once __DIR__ . '/modal_catat.php'; ?>
<?php require_once __DIR__ . '/modal_tx_detail.php'; ?>
</body>
</html>
