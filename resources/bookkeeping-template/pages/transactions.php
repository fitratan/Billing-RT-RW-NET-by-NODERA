<?php
$pageTitle = 'Transaksi';
$activeTab = 'transactions';
$pageFab   = [
    ['icon'=>'bi-plus-lg',           'label'=>'Catat Transaksi', 'onclick'=>'openCatatModalNew()', 'color'=>'var(--primary)'],
    ['icon'=>'bi-printer',           'label'=>'Cetak / PDF',    'onclick'=>'window.print()',       'color'=>'var(--primary)'],
    ['icon'=>'bi-file-earmark-excel','label'=>'Export Excel',   'onclick'=>'exportExcel()',        'color'=>'#22c55e'],
];
require_once __DIR__ . '/../include/header.php';

$data = getBkData();
$allTransactions = $data['transactions'] ?? [];
$categories      = $data['categories'] ?? [];
$searchQuery     = $_GET['q'] ?? $_GET['search'] ?? '';
$typeFilter      = $_GET['type'] ?? '';
$dateFilter      = $_GET['date'] ?? '';

// Group all transactions by month for instant client-side filtering
$grouped = [];
foreach ($allTransactions as $tx) {
    $month = substr($tx['transaction_date'], 0, 7);
    if (!isset($grouped[$month])) $grouped[$month] = ['income' => 0, 'expense' => 0, 'txs' => []];
    $grouped[$month]['txs'][] = $tx;
    if ($tx['type'] === 'income') $grouped[$month]['income'] += $tx['amount'];
    else $grouped[$month]['expense'] += $tx['amount'];
}
?>

<?php if (isset($_GET['msg'])): ?>
<div class="flash info">
    <i class="bi bi-info-circle-fill" style="flex-shrink:0;"></i>
    <span style="flex:1;">Data transaksi diperbarui!</span>
    <button class="flash-close" style="background:none;border:none;color:inherit;cursor:pointer;font-size:16px;">&times;</button>
</div>
<?php endif; ?>

<!-- Search & Filter bar (Mobile-Optimized with Collapsible Detail Filter Panel) -->
<div style="margin-bottom:16px;" class="no-print">
    <!-- Main Bar: Search Input + Filter Toggle Button -->
    <div style="display:flex;gap:8px;align-items:center;">
        <div style="position:relative;flex:1;">
            <i class="bi bi-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted-fg);"></i>
            <input type="text" id="filterSearch" value="<?= htmlspecialchars($searchQuery ?? '') ?>" placeholder="Cari nama / ket..." oninput="applyClientFilter()"
                style="width:100%;padding:10px 14px 10px 40px;border-radius:12px;border:1px solid var(--border);background:var(--card);color:var(--fg);font-family:'Poppins',sans-serif;font-size:13px;outline:none;">
        </div>
        <button type="button" id="btnToggleFilter" onclick="toggleFilterPanel()"
            style="position:relative;padding:10px 14px;border-radius:12px;border:1px solid var(--border);background:var(--card);color:var(--fg);cursor:pointer;font-family:'Poppins',sans-serif;font-size:13px;display:flex;align-items:center;gap:6px;white-space:nowrap;transition:all .15s;">
            <i class="bi bi-sliders"></i>
            <span style="font-weight:500;">Filter</span>
            <span id="filterDot" style="display:none;width:8px;height:8px;border-radius:50%;background:var(--primary);position:absolute;top:6px;right:6px;"></span>
        </button>
    </div>

    <!-- Active Filter Badges (Quick clear) -->
    <div id="activeFilterPills" style="display:none;margin-top:8px;flex-wrap:wrap;gap:6px;align-items:center;">
        <span id="pillDate" style="display:none;padding:4px 10px;border-radius:99px;background:var(--muted);color:var(--fg);font-size:11px;font-weight:500;align-items:center;gap:6px;">
            <i class="bi bi-calendar3"></i> <span id="pillDateText"></span>
            <i class="bi bi-x-lg" onclick="clearDateFilter()" style="cursor:pointer;opacity:.7;"></i>
        </span>
        <span id="pillType" style="display:none;padding:4px 10px;border-radius:99px;background:var(--muted);color:var(--fg);font-size:11px;font-weight:500;align-items:center;gap:6px;">
            <i class="bi bi-tag"></i> <span id="pillTypeText"></span>
            <i class="bi bi-x-lg" onclick="clearTypeFilter()" style="cursor:pointer;opacity:.7;"></i>
        </span>
    </div>

    <!-- Collapsible Filter Detail Panel -->
    <div id="filterPanel" class="card" style="display:none;margin-top:10px;padding:16px;background:var(--card);border:1px solid var(--border);border-radius:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <h4 style="margin:0;font-size:13px;font-weight:600;color:var(--fg);">Filter Detail</h4>
            <button type="button" onclick="resetAllFilters()" style="background:none;border:none;color:var(--primary);font-size:12px;font-weight:600;cursor:pointer;padding:0;">
                Reset Filter
            </button>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
                <label style="display:block;font-size:11px;font-weight:500;color:var(--muted-fg);margin-bottom:4px;">Jenis Transaksi</label>
                <select id="filterType" style="width:100%;padding:9px 12px;border-radius:10px;border:1px solid var(--border);background:var(--bg);color:var(--fg);font-family:'Poppins',sans-serif;font-size:13px;outline:none;" onchange="applyClientFilter()">
                    <option value="">Semua Jenis</option>
                    <option value="income" <?= (($typeFilter ?? '') === 'income') ? 'selected' : '' ?>>Pemasukan (+)</option>
                    <option value="expense" <?= (($typeFilter ?? '') === 'expense') ? 'selected' : '' ?>>Pengeluaran (-)</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:500;color:var(--muted-fg);margin-bottom:4px;">Tanggal Spesifik</label>
                <input type="date" id="filterDate" value="<?= htmlspecialchars($dateFilter ?? '') ?>" onchange="applyClientFilter()" style="width:100%;padding:8px 10px;border-radius:10px;border:1px solid var(--border);background:var(--bg);color:var(--fg);font-family:'Poppins',sans-serif;font-size:13px;outline:none;">
            </div>
        </div>
    </div>
</div>

<!-- Daily Summary Card (Shown when Date Filter is active, matches month-card design) -->
<div id="dailySummaryCard" class="card" style="display:none;margin-bottom:16px;overflow:hidden;">
    <div style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
        <div>
            <h3 id="dailySummaryTitle" style="margin:0;font-size:14px;font-weight:700;color:var(--fg);">Total Tanggal</h3>
            <p id="dailySummaryCount" style="margin:4px 0 0;font-size:11px;color:var(--muted-fg);">0 transaksi</p>
        </div>
        <div style="text-align:right;">
            <p id="dailySummaryNet" style="margin:0;font-size:14px;font-weight:700;color:var(--primary);">Rp 0</p>
        </div>
    </div>
    <div style="padding:0 20px 16px;">
        <div style="display:flex;gap:12px;padding-top:12px;border-top:1px dashed var(--border);">
            <div style="flex:1;">
                <p style="margin:0;font-size:10px;color:var(--muted-fg);">Pemasukan</p>
                <p id="dailySummaryIncome" style="margin:2px 0 0;font-size:13px;font-weight:600;color:#22c55e;">Rp 0</p>
            </div>
            <div style="flex:1;">
                <p style="margin:0;font-size:10px;color:var(--muted-fg);">Pengeluaran</p>
                <p id="dailySummaryExpense" style="margin:2px 0 0;font-size:13px;font-weight:600;color:#ef4444;">Rp 0</p>
            </div>
        </div>
    </div>
</div>

<div id="emptyTxState" class="card" style="display:<?= empty($grouped) ? 'block' : 'none' ?>;">
    <div style="padding:40px 20px;text-align:center;">
        <i class="bi bi-inbox" style="font-size:40px;color:var(--muted-fg);opacity:.4;display:block;margin-bottom:8px;"></i>
        <p style="font-size:12px;color:var(--muted-fg);margin:0;">Tidak ada transaksi ditemukan.</p>
    </div>
</div>

<!-- Tx data injected as JSON for fast virtual rendering -->
<div id="txMonthContainer"></div>
<script id="txDataRaw" type="application/json"><?php echo json_encode($grouped, JSON_UNESCAPED_UNICODE); ?></script>

<script>
(function() {
    var raw = document.getElementById('txDataRaw');
    if (!raw) return;
    var grouped;
    try { grouped = JSON.parse(raw.textContent); } catch(e) { return; }

    var container = document.getElementById('txMonthContainer');
    var emptyEl   = document.getElementById('emptyTxState');
    var months    = Object.keys(grouped);

    if (!months.length) {
        if (emptyEl) emptyEl.style.display = 'block';
        return;
    }
    if (emptyEl) emptyEl.style.display = 'none';

    function fmtRp(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }
    function fmtDate(d) {
        var months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        var p = d.split('-');
        return p[2] + ' ' + months[parseInt(p[1],10)-1] + ' ' + p[0];
    }
    function monthLabel(m) {
        var nm = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        var p = m.split('-'); return nm[parseInt(p[1],10)-1] + ' ' + p[0];
    }

    // Build card headers (lightweight, no rows yet)
    months.forEach(function(month, idx) {
        var data = grouped[month];
        var net  = (data.income||0) - (data.expense||0);
        var cardId = 'month_card_' + month.replace(/-/g,'_');
        var netColor = net >= 0 ? 'var(--primary)' : '#ef4444';
        var card = document.createElement('div');
        card.className = 'card month-card';
        card.id = 'card_' + cardId;
        card.style.cssText = 'margin-bottom:16px;overflow:hidden;';
        card.setAttribute('data-month', month);
        card.innerHTML =
            '<div class="card-clickable" id="hdr_'+cardId+'" onclick="toggleMonthCard(\''+cardId+'\')" '+
            'style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid transparent;transition:background .15s;">'+
                '<div>'+
                    '<h3 style="margin:0;font-size:14px;font-weight:700;color:var(--fg);">'+monthLabel(month)+'</h3>'+
                    '<p style="margin:4px 0 0;font-size:11px;color:var(--muted-fg);" class="month-count">'+data.txs.length+' transaksi</p>'+
                '</div>'+
                '<div style="display:flex;align-items:center;gap:12px;">'+
                    '<div style="text-align:right;">'+
                        '<p style="margin:0;font-size:14px;font-weight:700;color:'+netColor+';" class="month-net">'+fmtRp(net)+'</p>'+
                        '<i class="bi bi-chevron-down toggle-icon" id="icon_'+cardId+'" style="font-size:12px;color:var(--muted-fg);transition:transform .2s;display:inline-block;margin-top:4px;"></i>'+
                    '</div>'+
                    '<div style="display:flex;align-items:center;gap:2px;" class="no-print">'+
                        '<button type="button" onclick="event.stopPropagation();printSingleMonthCard(\'card_'+cardId+'\')" class="btn btn-ghost btn-sm" title="Cetak" style="color:var(--primary);padding:6px;border-radius:8px;">'+
                            '<i class="bi bi-printer" style="font-size:16px;"></i>'+
                        '</button>'+
                        '<button type="button" onclick="event.stopPropagation();deleteMonthCard(\''+month+'\',\''+monthLabel(month)+'\')" class="btn btn-ghost btn-sm" title="Hapus Bulan Ini" style="color:#ef4444;padding:6px;border-radius:8px;">'+
                            '<i class="bi bi-trash" style="font-size:16px;"></i>'+
                        '</button>'+
                    '</div>'+
                '</div>'+
            '</div>'+
            '<div id="'+cardId+'" style="display:none;padding:0 20px 20px;border-top:1px solid var(--border);" data-rendered="0"></div>';
        container.appendChild(card);
    });

    // Lazy-render rows for a month when card is first opened
    function renderMonthRows(cardId, month) {
        var body = document.getElementById(cardId);
        if (!body || body.getAttribute('data-rendered') === '1') return;
        body.setAttribute('data-rendered', '1');
        var data = grouped[month];
        var rows = '';
        rows += '<div style="display:flex;gap:12px;padding:12px 0 16px;margin-bottom:8px;border-bottom:1px dashed var(--border);">'+
            '<div style="flex:1;"><p style="margin:0;font-size:10px;color:var(--muted-fg);">Pemasukan</p><p style="margin:0;font-size:13px;font-weight:600;color:#22c55e;" class="month-income">'+fmtRp(data.income||0)+'</p></div>'+
            '<div style="flex:1;"><p style="margin:0;font-size:10px;color:var(--muted-fg);">Pengeluaran</p><p style="margin:0;font-size:13px;font-weight:600;color:#ef4444;" class="month-expense">'+fmtRp(data.expense||0)+'</p></div>'+
        '</div>';
        (data.txs || []).forEach(function(tx) {
            var isIncome = tx.type === 'income';
            var color    = isIncome ? '#22c55e' : '#ef4444';
            var iconBg   = isIncome ? 'rgba(34,197,94,.1)' : 'rgba(239,68,68,.1)';
            var icon     = isIncome ? 'bi-arrow-down-left' : 'bi-arrow-up-right';
            var amtSign  = isIncome ? '+' : '-';
            var searchTxt= ((tx.category||'') + ' ' + (tx.description||'')).toLowerCase();
            var txJson   = encodeURIComponent(JSON.stringify(tx));
            rows +=
                '<div class="divide-row tx-row" onclick="handleRowClick(this)" '+
                'data-tx="'+txJson+'" '+
                'data-type="'+tx.type+'" data-amount="'+tx.amount+'" data-date="'+tx.transaction_date+'" data-search="'+searchTxt.replace(/"/g,'')+'" '+
                'style="display:flex;align-items:center;gap:12px;padding:12px 0;cursor:pointer;border-radius:8px;margin:0 -4px;padding-left:4px;padding-right:4px;transition:background .15s;" '+
                'onmouseover="this.style.background=\'var(--muted)\'" onmouseout="this.style.background=\'transparent\'">'+
                    '<div style="width:32px;height:32px;border-radius:8px;background:'+iconBg+';color:'+color+';display:flex;align-items:center;justify-content:center;flex-shrink:0;">'+
                        '<i class="bi '+icon+'"></i>'+
                    '</div>'+
                    '<div style="flex:1;min-width:0;">'+
                        '<p style="margin:0;font-size:13px;font-weight:500;color:var(--fg);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+tx.category+'</p>'+
                        '<p style="margin:0;font-size:11px;color:var(--muted-fg);">'+fmtDate(tx.transaction_date)+(tx.description?' · '+tx.description:'')+'</p>'+
                    '</div>'+
                    '<span style="font-size:13px;font-weight:600;flex-shrink:0;color:'+color+';">'+amtSign+' '+fmtRp(tx.amount)+'</span>'+
                '</div>';
        });
        body.innerHTML = rows;
    }

    window.toggleMonthCard = function(id) {
        var body = document.getElementById(id);
        var icon = document.getElementById('icon_' + id);
        var hdr  = document.getElementById('hdr_' + id);
        if (!body) return;
        var isOpen = body.style.display !== 'none';
        if (!isOpen) {
            var month = document.getElementById('card_'+id).getAttribute('data-month');
            renderMonthRows(id, month);
            body.style.display = 'block';
            if (icon) icon.style.transform = 'rotate(180deg)';
            if (hdr)  hdr.style.background  = 'var(--muted)';
        } else {
            body.style.display = 'none';
            if (icon) icon.style.transform = 'rotate(0deg)';
            if (hdr)  hdr.style.background  = 'transparent';
        }
    };

    window.toggleFilterPanel = function() {
        var panel = document.getElementById('filterPanel');
        if (!panel) return;
        var isOpen = panel.style.display !== 'none';
        panel.style.display = isOpen ? 'none' : 'block';
    };

    window.clearDateFilter = function() {
        var dateEl = document.getElementById('filterDate');
        if (dateEl) dateEl.value = '';
        applyClientFilter();
    };

    window.clearTypeFilter = function() {
        var typeEl = document.getElementById('filterType');
        if (typeEl) typeEl.value = '';
        applyClientFilter();
    };

    window.resetAllFilters = function() {
        var dateEl = document.getElementById('filterDate');
        var typeEl = document.getElementById('filterType');
        if (dateEl) dateEl.value = '';
        if (typeEl) typeEl.value = '';
        applyClientFilter();
    };

    window.applyClientFilter = function() {
        var typeEl   = document.getElementById('filterType');
        var searchEl = document.getElementById('filterSearch');
        var dateEl   = document.getElementById('filterDate');
        var type   = typeEl   ? typeEl.value : '';
        var search = searchEl ? searchEl.value.toLowerCase().trim() : '';
        var dateVal= dateEl   ? dateEl.value : '';
        var totalVisible = 0;
        var filteredInc = 0, filteredExp = 0;

        // Update Active Filter Dot & Pills
        var filterDot = document.getElementById('filterDot');
        var btnFilter = document.getElementById('btnToggleFilter');
        var pillsContainer = document.getElementById('activeFilterPills');
        var pillDate = document.getElementById('pillDate');
        var pillDateText = document.getElementById('pillDateText');
        var pillType = document.getElementById('pillType');
        var pillTypeText = document.getElementById('pillTypeText');
        var hasActiveFilter = Boolean(type || dateVal);

        if (filterDot) filterDot.style.display = hasActiveFilter ? 'block' : 'none';
        if (btnFilter) {
            btnFilter.style.borderColor = hasActiveFilter ? 'var(--primary)' : 'var(--border)';
            btnFilter.style.color       = hasActiveFilter ? 'var(--primary)' : 'var(--fg)';
        }

        if (pillsContainer) pillsContainer.style.display = hasActiveFilter ? 'flex' : 'none';
        if (pillDate) {
            pillDate.style.display = dateVal ? 'inline-flex' : 'none';
            if (pillDateText && dateVal) pillDateText.innerText = fmtDate(dateVal);
        }
        if (pillType) {
            pillType.style.display = type ? 'inline-flex' : 'none';
            if (pillTypeText && type) pillTypeText.innerText = (type === 'income' ? 'Pemasukan' : 'Pengeluaran');
        }

        document.querySelectorAll('.month-card').forEach(function(card) {
            var month = card.getAttribute('data-month');
            var cardId = 'month_card_' + (month||'').replace(/-/g,'_');
            // Force render so we can filter rows
            renderMonthRows(cardId, month);

            var rows = card.querySelectorAll('.tx-row');
            var vis = 0, inc = 0, exp = 0;
            rows.forEach(function(row) {
                var t = row.getAttribute('data-type');
                var s = row.getAttribute('data-search') || '';
                var d = row.getAttribute('data-date') || '';
                var a = parseFloat(row.getAttribute('data-amount') || 0);
                var ok = (!type || t === type) && (!search || s.includes(search)) && (!dateVal || d === dateVal);
                row.style.display = ok ? 'flex' : 'none';
                if (ok) {
                    vis++;
                    totalVisible++;
                    if (t==='income') { inc+=a; filteredInc+=a; }
                    else { exp+=a; filteredExp+=a; }
                }
            });
            card.style.display = vis > 0 ? 'block' : 'none';
            var cEl = card.querySelector('.month-count');
            if (cEl) cEl.innerText = vis + ' transaksi';
            var net = inc - exp;
            var nEl = card.querySelector('.month-net');
            if (nEl) { nEl.innerText = fmtRp(net); nEl.style.color = net>=0?'var(--primary)':'#ef4444'; }

            // Auto expand month card if date filter is active
            if (dateVal && vis > 0) {
                var body = document.getElementById(cardId);
                if (body && body.style.display === 'none') {
                    toggleMonthCard(cardId);
                }
            }
        });

        // Update Daily Summary Banner
        var summaryCard = document.getElementById('dailySummaryCard');
        if (summaryCard) {
            if (dateVal) {
                summaryCard.style.display = 'block';
                var sTitle = document.getElementById('dailySummaryTitle');
                var sCount = document.getElementById('dailySummaryCount');
                var sNet   = document.getElementById('dailySummaryNet');
                var sInc   = document.getElementById('dailySummaryIncome');
                var sExp   = document.getElementById('dailySummaryExpense');
                var fNet   = filteredInc - filteredExp;
                if (sTitle) sTitle.innerText = 'Total Tanggal ' + fmtDate(dateVal);
                if (sCount) sCount.innerText = totalVisible + ' transaksi';
                if (sNet)  { sNet.innerText = fmtRp(fNet); sNet.style.color = fNet >= 0 ? 'var(--primary)' : '#ef4444'; }
                if (sInc)  sInc.innerText = fmtRp(filteredInc);
                if (sExp)  sExp.innerText = fmtRp(filteredExp);
            } else {
                summaryCard.style.display = 'none';
            }
        }

        if (emptyEl) emptyEl.style.display = totalVisible === 0 ? 'block' : 'none';
        var params = new URLSearchParams(window.location.search);
        params.set('page','transactions');
        if (type) params.set('type',type); else params.delete('type');
        if (search) params.set('search',search); else params.delete('search');
        if (dateVal) params.set('date',dateVal); else params.delete('date');
        window.history.replaceState(null,'','index.php?'+params.toString());
    };

    window.deleteMonthCard = function(month, label) {
        if (confirm('Hapus seluruh transaksi untuk bulan ' + label + '? Tindakan ini tidak dapat dibatalkan.')) {
            window.location.href = 'index.php?delete_month=' + month;
        }
    };

    window.printSingleMonthCard = function(cardId) {
        var allCards = document.querySelectorAll('.month-card');
        allCards.forEach(function(c) {
            if (c.id !== cardId) c.classList.add('no-print');
            else { var innerId = cardId.replace('card_',''); var inner = document.getElementById(innerId); if(inner) inner.style.display='block'; }
        });
        window.print();
        setTimeout(function() { allCards.forEach(function(c){c.classList.remove('no-print');}); }, 1000);
    };

    // Open most-recent month on load
    var firstCardId = 'month_card_' + months[0].replace(/-/g,'_');
    toggleMonthCard(firstCardId);

    // Apply any URL filters on load
    var urlParams = new URLSearchParams(window.location.search);
    var urlType = urlParams.get('type') || '';
    var urlSearch = urlParams.get('search') || '';
    if (urlType || urlSearch) {
        var typeEl = document.getElementById('filterType');
        var searchEl = document.getElementById('filterSearch');
        if (typeEl && urlType) typeEl.value = urlType;
        if (searchEl && urlSearch) searchEl.value = urlSearch;
        applyClientFilter();
    }
})();
</script>

<?php require_once __DIR__ . '/../include/footer.php'; ?>
