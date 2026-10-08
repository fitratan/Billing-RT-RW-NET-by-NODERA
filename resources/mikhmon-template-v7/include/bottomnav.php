<?php
/*
 *  MIKHMON Mobile Bottom Navigation Bar (4 Buttons Native Theme)
 */
$isLoginPage = (isset($id) && $id === "login") || (isset($_GET['id']) && $_GET['id'] === "login") || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'id=login') !== false);
$isWarungScript = (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'warung.php') || (basename($_SERVER['PHP_SELF'] ?? '') === 'warung.php') || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'warung.php') !== false);
$showAdminBottomNav = !$isWarungScript && isset($_SESSION["mikhmon"]) && !$isLoginPage;

$isHotspotSession = !empty($session);
$activeTheme = $theme ?? $_SESSION['theme'] ?? 'dark';
$activeThemeColor = $themecolor ?? $_SESSION['themecolor'] ?? '#3a4149';
$isDarkTheme = ($activeTheme === 'dark');

if ($isHotspotSession) {
    $b_gen = ($hotspotuser == "generate") ? "active" : "";
    $b_users = (($hotspot == "users" || $hotspotuser == "add" || !empty($userbyname) || !empty($userbyprofile) || (!empty($hotspotuser) && $hotspotuser != "generate")) && $hotspotuser != "generate") ? "active" : "";
    $b_dash = ($hotspot == "dashboard" || (empty($hotspot) && empty($hotspotuser) && empty($report) && empty($sys) && empty($minterface) && empty($interface) && empty($ppp) && empty($userprofile))) ? "active" : "";
} else {
    $b_sessions = ($id == "sessions" || empty($id)) ? "active" : "";
    $b_addrouter = ($id == "settings" && (isset($_GET['router']) || (isset($_GET['session']) && $_GET['session'] == "new"))) ? "active" : "";
    $b_about = ($id == "about") ? "active" : "";
}
?>

<?php if ($showAdminBottomNav): ?>
<!-- MIKHMON Mobile Bottom Navigation Bar (4 Buttons) -->
<div id="mikhmonBottomNav" class="navbar mikhmon-bottom-nav">
<?php if ($isHotspotSession): ?>
  <a href="./?session=<?= htmlspecialchars($session); ?>" class="<?= $b_dash; ?>">
    <i class="fa fa-tachometer"></i>
    <span><?= !empty($_dashboard) ? $_dashboard : 'Dashboard'; ?></span>
  </a>
  <a href="./?hotspot=users&profile=all&session=<?= htmlspecialchars($session); ?>" class="<?= $b_users; ?>">
    <i class="fa fa-users"></i>
    <span><?= !empty($_users) ? $_users : 'Pengguna'; ?></span>
  </a>
  <a href="./?hotspot-user=generate&session=<?= htmlspecialchars($session); ?>" class="<?= $b_gen; ?>">
    <i class="fa fa-user-plus"></i>
    <span><?= !empty($_generate) ? $_generate : 'Generate'; ?></span>
  </a>
  <a href="javascript:void(0)" id="bottomNavToggleMenu">
    <i class="fa fa-bars"></i>
    <span><?= !empty($_menu) ? $_menu : 'Menu'; ?></span>
  </a>
<?php else: ?>
  <a href="./admin.php?id=sessions" class="<?= $b_sessions; ?>">
    <i class="fa fa-server"></i>
    <span><?= !empty($_routers) ? $_routers : 'Router'; ?></span>
  </a>
  <a href="./admin.php?id=settings&router=new-<?= rand(1111,9999) ?>" class="<?= $b_addrouter; ?>">
    <i class="fa fa-plus-circle"></i>
    <span><?= !empty($_add) ? $_add : 'Tambah'; ?></span>
  </a>
  <a href="./admin.php?id=about" class="<?= $b_about; ?>">
    <i class="fa fa-info-circle"></i>
    <span><?= !empty($_about) ? $_about : 'Tentang'; ?></span>
  </a>
  <a href="./admin.php?id=logout" onclick="return confirm('<?= !empty($_logout_confirm) ? $_logout_confirm : (!empty($_confirm) ? $_confirm : 'Logout from Mikhmon?'); ?>')">
    <i class="fa fa-sign-out"></i>
    <span><?= !empty($_logout) ? $_logout : 'Keluar'; ?></span>
  </a>
<?php endif; ?>
</div>
<?php endif; ?>

<style>
/* ====================================================
   GLOBAL ANTI-OVERFLOW & DESKTOP RESET
   ==================================================== */
*, *::before, *::after {
  box-sizing: border-box !important;
}

html, body {
  width: 100% !important;
  max-width: 100vw !important;
  overflow-x: hidden !important;
  margin: 0 !important;
  padding: 0 !important;
}

.wrapper, .main-container {
  width: 100% !important;
  max-width: 100% !important;
  box-sizing: border-box !important;
}

#main {
  box-sizing: border-box !important;
  min-height: 100vh;
}

@media screen and (min-width: 800px) {
  body.sidebar-open #main {
    margin-left: 210px !important;
    width: calc(100% - 210px) !important;
    max-width: calc(100% - 210px) !important;
  }
  body.sidebar-closed #main {
    margin-left: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
  }
}

@media screen and (max-width: 799px) {
  #main {
    margin-left: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
  }
}

.mikhmon-bottom-nav,
.top-kebab-btn,
#topKebabBtn,
.navbar-right .top-kebab-btn,
.navbar .top-kebab-btn,
.top-kebab-dropdown,
#topKebabDropdown {
  display: none !important;
}

@media screen and (min-width: 769px) {
  .mikhmon-bottom-nav,
  .top-kebab-btn,
  #topKebabBtn,
  .navbar-right .top-kebab-btn,
  .navbar .top-kebab-btn,
  .top-kebab-dropdown,
  #topKebabDropdown {
    display: none !important;
  }
}

/* Equal height cards for Mikhmon Dashboard #r_1 */
#r_1 {
  display: flex !important;
  flex-wrap: wrap !important;
  align-items: stretch !important;
  width: 100% !important;
  margin: 0 0 10px 0 !important;
}

#r_1 .col-4 {
  display: flex !important;
  flex-direction: column !important;
  box-sizing: border-box !important;
  padding: 0 !important;
  margin: 0 !important;
}

@media screen and (min-width: 751px) {
  #r_1 .col-4 {
    width: 33.333333% !important;
    max-width: 33.333333% !important;
    float: none !important;
  }
}

@media screen and (max-width: 750px) {
  #r_1 .col-4 {
    width: 100% !important;
    max-width: 100% !important;
    float: none !important;
  }
}

#r_1 .box {
  flex: 1 1 auto !important;
  display: flex !important;
  align-items: center !important;
  margin: 4px !important;
  padding: 10px 12px !important;
  box-sizing: border-box !important;
  min-height: 75px !important;
  border-radius: 3px !important;
}

#r_1 .box-group {
  display: flex !important;
  align-items: center !important;
  width: 100% !important;
  gap: 10px !important;
}

#r_1 .box-group-icon {
  flex: 0 0 44px !important;
  width: 44px !important;
  height: 44px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  font-size: 26px !important;
  margin: 0 !important;
  padding: 0 !important;
  border-radius: 3px !important;
}

#r_1 .box-group-area {
  flex: 1 1 auto !important;
  min-width: 0 !important;
  padding: 0 !important;
  line-height: 1.45 !important;
  font-size: 13px !important;
  word-break: break-word !important;
}

/* Equal height & symmetrical rectangular tiles for Mikhmon Dashboard #r_2 (Hotspot Counters) */
#r_2.card {
  border-radius: 3px !important;
}

#r_2 .card-body {
  padding: 8px !important;
}

#r_2 .card-body > .row,
#r_2 .row {
  display: grid !important;
  grid-template-columns: repeat(4, 1fr) !important;
  gap: 8px !important;
  width: 100% !important;
  margin: 0 !important;
  box-sizing: border-box !important;
}

#r_2 .col-3,
#r_2 .col-box-6 {
  width: 100% !important;
  max-width: 100% !important;
  min-width: 0 !important;
  display: flex !important;
  flex-direction: column !important;
  box-sizing: border-box !important;
  padding: 0 !important;
  margin: 0 !important;
  float: none !important;
  clear: none !important;
}

@media screen and (max-width: 750px) {
  #r_2 .card-body > .row,
  #r_2 .row {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 8px !important;
  }
}

#r_2 .box {
  flex: 1 1 auto !important;
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  margin: 0 !important;
  padding: 12px 6px !important;
  box-sizing: border-box !important;
  width: 100% !important;
  max-width: 100% !important;
  height: 100% !important;
  min-height: 75px !important;
  border-radius: 3px !important;
  text-align: center !important;
  overflow: hidden !important;
  box-shadow: none !important;
}

#r_2 .box a {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  width: 100% !important;
  height: 100% !important;
  text-decoration: none !important;
  color: #ffffff !important;
  gap: 4px !important;
}

#r_2 .box h1 {
  font-size: 20px !important;
  font-weight: 700 !important;
  line-height: 1.15 !important;
  margin: 0 !important;
  padding: 0 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 5px !important;
  min-height: 24px !important;
  color: #ffffff !important;
}

#r_2 .box h1 span {
  font-size: 13px !important;
  font-weight: 500 !important;
  opacity: 0.95 !important;
}

#r_2 .box div {
  font-size: 11.5px !important;
  font-weight: 500 !important;
  line-height: 1.2 !important;
  margin: 0 !important;
  padding: 0 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 4px !important;
  white-space: nowrap !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
  max-width: 100% !important;
  color: #ffffff !important;
}

@media screen and (max-width: 768px) {
  /* ====================================================
     MOBILE VIEWPORT & RESPONSIVE LAYOUT
     ==================================================== */
  html, body {
    overflow-x: hidden !important;
    width: 100% !important;
    max-width: 100vw !important;
    position: relative !important;
  }

  .wrapper, #main, .main-container {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: hidden !important;
    box-sizing: border-box !important;
  }

  .row {
    margin: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  /* Grid Floats Fix on Mobile */
  [class*=col-]:not(.col-box-6):not(#r_1 .col-4):not(#r_2 .col-3):not(#r_2 .col-box-6) {
    width: 100% !important;
    max-width: 100% !important;
    float: none !important;
    clear: both !important;
    margin: 0 !important;
    padding: 0 !important;
    box-sizing: border-box !important;
  }

  .col-box-6:not(#r_2 .col-box-6) {
    width: 50% !important;
    max-width: 50% !important;
    float: left !important;
    box-sizing: border-box !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  /* Card & Box Boundary Safety */
  .card {
    width: calc(100% - 12px) !important;
    max-width: calc(100% - 12px) !important;
    margin: 8px auto !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    border-radius: 3px !important;
  }

  .box:not(#r_1 .box):not(#r_2 .box) {
    width: calc(100% - 12px) !important;
    max-width: calc(100% - 12px) !important;
    margin: 8px auto !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    border-radius: 3px !important;
  }

  .card-header {
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 8px !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    padding: 12px 14px !important;
  }

  .card-header h3, .card-title {
    font-size: 14px !important;
    line-height: 1.4 !important;
    word-break: break-word !important;
    white-space: normal !important;
    margin: 0 !important;
    max-width: 100% !important;
  }

  .card-header h3 span, .card-header a {
    display: inline-block !important;
    word-break: break-word !important;
  }

  .card-body {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    padding: 12px 14px !important;
    overflow-x: hidden !important;
  }

  /* Tables Horizontal Scroll Safety on Mobile */
  .overflow,
  .card-body > table,
  form > table,
  .box > table,
  .table-responsive {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    display: block !important;
    box-sizing: border-box !important;
  }

  .table, table {
    min-width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  /* Form & Input Groups on Mobile */
  .input-group {
    display: flex !important;
    flex-wrap: wrap !important;
    width: 100% !important;
    max-width: 100% !important;
  }

  .input-group > div, [class*=input-group-] {
    box-sizing: border-box !important;
  }

  .form-control, input, select, textarea {
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  .btn {
    max-width: 100% !important;
    white-space: normal !important;
    margin: 3px 2px !important;
  }

  #trafficMonitor, .chart, .highcharts-container {
    width: 100% !important;
    max-width: 100% !important;
    overflow: hidden !important;
  }

  /* Bottom Nav Bar */
  .mikhmon-bottom-nav {
    display: flex !important;
    position: fixed !important;
    bottom: 0 !important;
    top: auto !important;
    left: 0 !important;
    right: 0 !important;
    width: 100% !important;
    height: 52px !important;
    z-index: 999999 !important;
    align-items: stretch !important;
    justify-content: space-around !important;
    padding: 0 !important;
    padding-bottom: env(safe-area-inset-bottom, 0px) !important;
    box-sizing: border-box !important;
    border-top: 1px solid rgba(0, 0, 0, 0.2) !important;
    border-bottom: none !important;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.25) !important;
  }

  .mikhmon-bottom-nav a {
    position: relative !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    flex: 1 !important;
    height: 100% !important;
    background: transparent !important;
    color: #f2f2f2 !important;
    opacity: 0.65 !important;
    text-decoration: none !important;
    font-size: 11px !important;
    font-weight: 500 !important;
    padding: 3px 0 1px !important;
    cursor: pointer !important;
    transition: opacity 0.15s ease !important;
    -webkit-tap-highlight-color: transparent !important;
    line-height: 1.2 !important;
    float: none !important;
  }

  .mikhmon-bottom-nav a i {
    font-size: 18px !important;
    margin-bottom: 2px !important;
    display: block !important;
  }

  .mikhmon-bottom-nav a:hover,
  .mikhmon-bottom-nav a:active {
    opacity: 1 !important;
    background: transparent !important;
  }

  .mikhmon-bottom-nav a.active {
    opacity: 1 !important;
    background: transparent !important;
    font-weight: 600 !important;
  }

  .mikhmon-bottom-nav a.active::before {
    content: '' !important;
    position: absolute !important;
    top: 0 !important;
    left: 20% !important;
    right: 20% !important;
    height: 3px !important;
    background-color: #ffffff !important;
    border-radius: 0 0 3px 3px !important;
  }

  /* Full-Height Mobile Drawer Sidenav */
  .sidenav {
    position: fixed !important;
    top: 0 !important;
    bottom: 0 !important;
    left: 0 !important;
    height: 100vh !important;
    height: 100dvh !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    padding-top: env(safe-area-inset-top, 0px) !important;
    padding-bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
    z-index: 999999 !important;
    box-sizing: border-box !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    -webkit-overflow-scrolling: touch !important;
    box-shadow: 4px 0 25px rgba(0, 0, 0, 0.35) !important;
    transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
  }

  .sidenav .card-header {
    margin: 0 0 10px 0 !important;
    padding: 14px 10px !important;
    border-radius: 0 !important;
  }

  /* Top Navbar on Mobile */
  .navbar:not(.mikhmon-bottom-nav) {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 0 12px !important;
    box-sizing: border-box !important;
    height: 50px !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    width: 100% !important;
    z-index: 9999 !important;
  }

  .navbar-left {
    display: flex !important;
    align-items: center !important;
    float: none !important;
    height: 100% !important;
    min-width: 0 !important;
    flex: 1 1 auto !important;
    overflow: hidden !important;
  }

  #brand, .navbar #brand {
    display: none !important;
  }

  #cpage, .navbar #cpage {
    display: flex !important;
    align-items: center !important;
    font-size: 15px !important;
    font-weight: 700 !important;
    padding: 0 !important;
    height: 100% !important;
    color: #ffffff !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    max-width: 180px !important;
    text-decoration: none !important;
    letter-spacing: -0.2px !important;
  }

  body #openNav, body #closeNav,
  body.sidebar-open #openNav, body.sidebar-open #closeNav,
  body.sidebar-closed #openNav, body.sidebar-closed #closeNav,
  #openNav, #closeNav, .navbar #openNav, .navbar #closeNav {
    display: none !important;
  }

  .navbar-right {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    float: none !important;
    height: 100% !important;
    flex-shrink: 0 !important;
    gap: 6px !important;
  }

  .navbar-right .stheme,
  .navbar-right .slang,
  .navbar-right select.connect,
  .navbar-right select.ses,
  .navbar-right a#logout,
  .navbar-right a[title="Idle Timeout"],
  .navbar-right #timer {
    display: none !important;
  }

  .navbar-right a, .navbar-right select, .navbar-right span {
    float: none !important;
    margin: 0 !important;
  }

  /* 3-Dots Kebab Button */
  .navbar-right .top-kebab-btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 44px !important;
    height: 50px !important;
    background: transparent !important;
    border: none !important;
    color: #ffffff !important;
    text-decoration: none !important;
    font-size: 20px !important;
    cursor: pointer !important;
    padding: 0 !important;
    margin: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    outline: none !important;
    transition: background 0.15s ease, opacity 0.15s ease !important;
    -webkit-tap-highlight-color: transparent !important;
  }

  .navbar-right .top-kebab-btn i {
    color: inherit !important;
    font-size: 20px !important;
    line-height: 1 !important;
  }

  .navbar-right .top-kebab-btn:hover,
  .navbar-right .top-kebab-btn:active {
    background: rgba(0, 0, 0, 0.15) !important;
    color: #ffffff !important;
  }

<?php if ($isDarkTheme): ?>
  /* ====================================================
     THEME-AWARE KEBAB DROPDOWN (DARK THEME)
     ==================================================== */
  .top-kebab-dropdown {
    position: fixed !important;
    top: 54px !important;
    right: 12px !important;
    width: 270px !important;
    max-width: calc(100vw - 24px) !important;
    background: #343b41 !important;
    color: #f3f4f5 !important;
    border: 1px solid #23282c !important;
    border-radius: 12px !important;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.6) !important;
    padding: 14px !important;
    z-index: 999999 !important;
    box-sizing: border-box !important;
    font-family: inherit !important;
  }

  .kebab-item-header {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    color: #ffffff !important;
    padding-bottom: 8px !important;
    margin-bottom: 10px !important;
    border-bottom: 1px solid #23282c !important;
  }

  .kebab-item-header .close-kebab-btn {
    font-size: 20px !important;
    line-height: 1 !important;
    color: #cbd5e1 !important;
    text-decoration: none !important;
    padding: 0 4px !important;
  }

  .kebab-section {
    margin-bottom: 10px !important;
  }

  .kebab-section label {
    display: block !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    color: #94a3b8 !important;
    margin-bottom: 4px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
  }

  .kebab-section select {
    width: 100% !important;
    height: 36px !important;
    background: #2f353a !important;
    color: #f3f4f5 !important;
    border: 1px solid #4b5563 !important;
    border-radius: 6px !important;
    padding: 0 8px !important;
    font-size: 13px !important;
    outline: none !important;
    box-sizing: border-box !important;
  }

  .kebab-divider {
    height: 1px !important;
    background: #23282c !important;
    margin: 10px 0 !important;
  }

  .kebab-logout-btn {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    width: 100% !important;
    height: 36px !important;
    background: rgba(220, 53, 69, 0.25) !important;
    border: 1px solid rgba(220, 53, 69, 0.5) !important;
    border-radius: 6px !important;
    color: #ff8591 !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    text-decoration: none !important;
    box-sizing: border-box !important;
    transition: background 0.15s ease !important;
  }

  .kebab-logout-btn:active, .kebab-logout-btn:hover {
    background: rgba(220, 53, 69, 0.45) !important;
    color: #ffffff !important;
  }

<?php else: ?>
  /* ====================================================
     THEME-AWARE KEBAB DROPDOWN (LIGHT / COLOR THEME)
     ==================================================== */
  .top-kebab-dropdown {
    position: fixed !important;
    top: 54px !important;
    right: 12px !important;
    width: 270px !important;
    max-width: calc(100vw - 24px) !important;
    background: #ffffff !important;
    color: #1e293b !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 12px !important;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18) !important;
    padding: 14px !important;
    z-index: 999999 !important;
    box-sizing: border-box !important;
    font-family: inherit !important;
  }

  .kebab-item-header {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    color: <?= $activeThemeColor ?: '#0284c7' ?> !important;
    padding-bottom: 8px !important;
    margin-bottom: 10px !important;
    border-bottom: 1px solid #e2e8f0 !important;
  }

  .kebab-item-header .close-kebab-btn {
    font-size: 20px !important;
    line-height: 1 !important;
    color: #64748b !important;
    text-decoration: none !important;
    padding: 0 4px !important;
  }

  .kebab-section {
    margin-bottom: 10px !important;
  }

  .kebab-section label {
    display: block !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    color: #64748b !important;
    margin-bottom: 4px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
  }

  .kebab-section select {
    width: 100% !important;
    height: 36px !important;
    background: #f8fafc !important;
    color: #1e293b !important;
    border: 1.5px solid #cbd5e1 !important;
    border-radius: 6px !important;
    padding: 0 8px !important;
    font-size: 13px !important;
    outline: none !important;
    box-sizing: border-box !important;
  }

  .kebab-divider {
    height: 1px !important;
    background: #e2e8f0 !important;
    margin: 10px 0 !important;
  }

  .kebab-logout-btn {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    width: 100% !important;
    height: 36px !important;
    background: #fef2f2 !important;
    border: 1px solid #fca5a5 !important;
    border-radius: 6px !important;
    color: #dc2626 !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    text-decoration: none !important;
    box-sizing: border-box !important;
    transition: background 0.15s ease !important;
  }

  .kebab-logout-btn:active, .kebab-logout-btn:hover {
    background: #fee2e2 !important;
    color: #b91c1c !important;
  }
<?php endif; ?>

  .logout-text {
    display: none !important;
  }

  /* Content area spacing without excessive gap */
  #main {
    margin-top: 50px !important;
    padding-top: 4px !important;
    margin-left: 0 !important;
    transition: none !important;
    padding-bottom: calc(70px + env(safe-area-inset-bottom, 0px)) !important;
    box-sizing: border-box !important;
  }

  .main-container {
    padding: 4px 6px !important;
    box-sizing: border-box !important;
  }
}
</style>

<script>
function parseMikhmonRouterDate(dateStr, timeStr) {
  var months = {
    jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5,
    jul: 6, aug: 7, sep: 8, oct: 9, nov: 10, dec: 11
  };
  var now = new Date();
  var year = now.getUTCFullYear(), month = now.getUTCMonth(), day = now.getUTCDate();
  var hh = 0, mm = 0, ss = 0;

  if (timeStr) {
    var tp = timeStr.trim().split(':');
    hh = parseInt(tp[0], 10) || 0;
    mm = parseInt(tp[1], 10) || 0;
    ss = parseInt(tp[2], 10) || 0;
  }

  if (dateStr) {
    dateStr = dateStr.trim();
    if (dateStr.indexOf('-') !== -1) {
      var dp = dateStr.split('-');
      if (dp[0].length === 4) {
        year = parseInt(dp[0], 10) || year;
        month = (parseInt(dp[1], 10) || 1) - 1;
        day = parseInt(dp[2], 10) || day;
      } else {
        day = parseInt(dp[0], 10) || day;
        month = (parseInt(dp[1], 10) || 1) - 1;
        year = parseInt(dp[2], 10) || year;
      }
    } else if (dateStr.indexOf('/') !== -1) {
      var dp = dateStr.split('/');
      if (isNaN(dp[0])) {
        var key = dp[0].toLowerCase().substr(0, 3);
        month = months[key] !== undefined ? months[key] : month;
        day = parseInt(dp[1], 10) || day;
        year = parseInt(dp[2], 10) || year;
      } else if (isNaN(dp[1])) {
        day = parseInt(dp[0], 10) || day;
        var key = dp[1].toLowerCase().substr(0, 3);
        month = months[key] !== undefined ? months[key] : month;
        year = parseInt(dp[2], 10) || year;
      } else {
        month = (parseInt(dp[0], 10) || 1) - 1;
        day = parseInt(dp[1], 10) || day;
        year = parseInt(dp[2], 10) || year;
      }
    }
  }

  return Date.UTC(year, month, day, hh, mm, ss);
}

function initMikhmonLiveClock() {
  if (window.mikhmonClockInterval) {
    clearInterval(window.mikhmonClockInterval);
  }
  var $clock = $('.live-clock-time');
  if (!$clock.length) return;

  var dateStr = $clock.first().attr('data-date') || '';
  var timeStr = $clock.first().attr('data-time') || '';

  if (!timeStr) {
    var fullText = $clock.first().text().trim().split(' ');
    if (fullText.length >= 2) {
      dateStr = fullText[0];
      timeStr = fullText[1];
    } else {
      timeStr = fullText[0];
    }
  }

  var baseRouterUtc = parseMikhmonRouterDate(dateStr, timeStr);
  var clientStart = Date.now();

  window.mikhmonClockInterval = setInterval(function() {
    var elapsed = Date.now() - clientStart;
    var cur = new Date(baseRouterUtc + elapsed);

    var y = cur.getUTCFullYear();
    var m = String(cur.getUTCMonth() + 1).padStart(2, '0');
    var d = String(cur.getUTCDate()).padStart(2, '0');
    var hh = String(cur.getUTCHours()).padStart(2, '0');
    var mm = String(cur.getUTCMinutes()).padStart(2, '0');
    var ss = String(cur.getUTCSeconds()).padStart(2, '0');

    $('.live-clock-time').text(hh + ':' + mm + ':' + ss);
    $('.live-clock-date').text(y + '-' + m + '-' + d);
  }, 1000);
}

function openMikhmonSidenav() {
  var sidenav = document.getElementById("sidenav");
  if (!sidenav) return;
  
  sidenav.style.setProperty("width", "270px", "important");
  sidenav.style.setProperty("display", "block", "important");
  sidenav.style.setProperty("margin-top", "0px", "important");
  sidenav.style.setProperty("top", "0px", "important");
  sidenav.style.setProperty("height", "100vh", "important");
  sidenav.style.setProperty("z-index", "999999", "important");
  
  var menus = sidenav.querySelectorAll(".menu");
  for (var i = 0; i < menus.length; i++) {
    menus[i].style.setProperty("display", "block", "important");
  }
  
  var dropdownBtns = sidenav.querySelectorAll(".dropdown-btn");
  for (var j = 0; j < dropdownBtns.length; j++) {
    dropdownBtns[j].style.setProperty("display", "block", "important");
  }
  
  var overlay = document.getElementById("mikhmonNavOverlay");
  if (!overlay) {
    overlay = document.createElement("div");
    overlay.id = "mikhmonNavOverlay";
    overlay.style.cssText = "position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.55);z-index:999998;display:none;-webkit-tap-highlight-color:transparent;";
    document.body.appendChild(overlay);
    overlay.addEventListener("click", function() {
      closeMikhmonSidenav();
    });
  }
  overlay.style.display = "block";
}

function closeMikhmonSidenav() {
  var sidenav = document.getElementById("sidenav");
  if (!sidenav) return;
  
  sidenav.style.setProperty("width", "0", "important");
  
  var overlay = document.getElementById("mikhmonNavOverlay");
  if (overlay) {
    overlay.style.display = "none";
  }
}

function toggleMikhmonSidenav() {
  var sidenav = document.getElementById("sidenav");
  if (!sidenav) return;
  var w = parseInt(sidenav.style.width, 10) || (sidenav.offsetWidth > 50 ? sidenav.offsetWidth : 0);
  if (w > 50) {
    closeMikhmonSidenav();
  } else {
    openMikhmonSidenav();
  }
}

$(document).ready(function(){
  initMikhmonLiveClock();
  
  $(document).on("click", "#bottomNavToggleMenu", function(e){
    e.preventDefault();
    e.stopPropagation();
    toggleMikhmonSidenav();
  });

  $(document).on("click", "#sidenav a", function(e){
    var href = $(this).attr("href");
    if (href && href !== "javascript:void(0)" && href !== "#") {
      if ($(window).width() <= 768) {
        closeMikhmonSidenav();
      }
    }
  });

  $(document).on("click", function(e) {
    if ($(window).width() <= 768) {
      var $target = $(e.target);
      var sidenav = document.getElementById("sidenav");
      var isOpen = sidenav && (parseInt(sidenav.style.width, 10) > 50);
      
      if (isOpen && !$target.closest('#sidenav').length && !$target.closest('#bottomNavToggleMenu').length) {
        closeMikhmonSidenav();
      }
    }
  });

  // Mobile Topnav Kebab Menu Toggle Handlers
  $(document).off("click", "#topKebabBtn").on("click", "#topKebabBtn", function(e){
    e.preventDefault();
    e.stopPropagation();
    $("#topKebabDropdown").fadeToggle(150);
  });

  $(document).off("click", "#closeKebabBtn").on("click", "#closeKebabBtn", function(e){
    e.preventDefault();
    e.stopPropagation();
    $("#topKebabDropdown").fadeOut(150);
  });

  $(document).on("click", function(e){
    if (!$(e.target).closest("#topKebabDropdown, #topKebabBtn").length) {
      $("#topKebabDropdown").fadeOut(150);
    }
  });
});
</script>
