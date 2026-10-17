<?php
/*
 *		Copyright (C) 2018 Laksamadi Guko.
 *
 *		This program is free software; you can redistribute it and/or modify
 *		it under the terms of the GNU General Public License as published by
 *		the Free Software Foundation; either version 2 of the License, or
 *		(at your option) any later version.
 *
 *		This program is distributed in the hope that it will be useful,
 *		but WITHOUT ANY WARRANTY; without even the implied warranty of
 *		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.		See the
 *		GNU General Public License for more details.
 *
 *		You should have received a copy of the GNU General Public License
 *		along with this program.		If not, see <http://www.gnu.org/licenses/>.
 */
if (session_status() === PHP_SESSION_NONE) {
    $dir = __DIR__;
    $isRoot = (file_exists($dir . '/buy.php') || file_exists($dir . '/admin.php') || file_exists($dir . '/include/config.php'));
    $mikhRoot = $isRoot ? $dir : (file_exists(dirname($dir) . '/buy.php') || file_exists(dirname($dir) . '/admin.php') ? dirname($dir) : $dir);
    $instanceHash = substr(md5($mikhRoot), 0, 10);
    @session_name('MIKH_' . $instanceHash);
    $cookiePath = $isRoot 
        ? rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/') 
        : rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    @ini_set('session.cookie_path', !empty($cookiePath) ? $cookiePath : '/');
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if (PHP_VERSION_ID >= 70300) {
        @ini_set('session.cookie_samesite', 'Lax');
    }
    @ini_set('session.cookie_httponly', '1');
    if ($isSecure) {
        @ini_set('session.cookie_secure', '1');
    }
    @session_start();
}
// hide all error
error_reporting(0);
?>
<!DOCTYPE html>
<html lang="id">
	<head>
		<title>MIKHMON <?= !empty($hotspotname) ? htmlspecialchars($hotspotname) : ''; ?></title>
		<meta charset="utf-8">
		<meta http-equiv="cache-control" content="private" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<!-- Tell the browser to be responsive to screen width -->
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
		<!-- Theme color -->
		<meta name="theme-color" content="<?= !empty($themecolor) ? $themecolor : '#0B0E14'; ?>" />
		<!-- Font Awesome -->
		<link rel="stylesheet" type="text/css" href="css/font-awesome/css/font-awesome.min.css" />
		<!-- Mikhmon UI -->
		<link id="mikhmonUiCss" rel="stylesheet" href="css/mikhmon-ui.<?= !empty($theme) ? $theme : 'dark'; ?>.min.css?v=3.20.2">
		<!-- favicon & PWA -->
		<link rel="icon" type="image/png" href="./img/favicon.png" />
		<link rel="apple-touch-icon" sizes="192x192" href="./img/icon-192.png" />
		<link rel="apple-touch-icon" sizes="512x512" href="./img/icon-512.png" />
<?php
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
$scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';

$isWarung = (defined('IS_WARUNG_PAGE') && IS_WARUNG_PAGE)
    || (!empty($isWarungPage))
    || (strpos($scriptName, 'warung') !== false)
    || (strpos($scriptFile, 'warung') !== false)
    || (preg_match('#/(warung|reseller)(\.php|/|\?|$)#i', $reqUri));

$isBuy = (defined('IS_BUY_PAGE') && IS_BUY_PAGE)
    || (!empty($isBuyPage))
    || (strpos($scriptName, 'buy') !== false)
    || (strpos($scriptFile, 'buy') !== false)
    || (preg_match('#/(buy|beli)(\.php|/|\?|$)#i', $reqUri));

if ($isWarung) {
    $pwaManifest = './manifest-warung.json?v=4';
    $pwaTitle = 'WARUNG VOUCHER';
} elseif ($isBuy) {
    $pwaManifest = './manifest-buy.json?v=4';
    $pwaTitle = 'BELI VOUCHER';
} else {
    $pwaManifest = './manifest.json?v=4';
    $pwaTitle = 'MIKHMON ADMIN';
}
?>
		<link rel="manifest" href="<?= $pwaManifest; ?>" />
		<meta name="apple-mobile-web-app-status-bar-style" content="default" />
		<meta name="apple-mobile-web-app-title" content="<?= $pwaTitle; ?>" />

		<!-- jQuery -->
		<script src="js/jquery.min.js"></script>
		<!-- pace -->
		<link id="mikhmonPaceCss" href="css/pace.<?= !empty($theme) ? $theme : 'dark'; ?>.css" rel="stylesheet" />
		<script src="js/pace.min.js"></script>
		<!-- Mikhmon Universal Thermal Driver -->
		<script src="js/mikhmon-thermal.js?v=<?= filemtime(__DIR__ . '/../js/mikhmon-thermal.js') ?: time(); ?>"></script>

		<!-- PWA Service Worker & Install Prompt Listener -->
		<script>
		window._mikhmonSwitching = false;

		window.mikhmonSwitchTheme = function(theme) {
			if (!theme || window._mikhmonSwitching) return;
			var match = String(theme).match(/set-theme=([^&]+)/);
			var actual = match ? match[1] : theme;
			if (!actual) return;
			window._mikhmonSwitching = true;

			// Instant client-side visual switch (0ms)
			try {
				document.cookie = 'mikhmon_theme=' + encodeURIComponent(actual) + '; path=/; max-age=31536000; SameSite=Lax';
				var uiCss = document.getElementById('mikhmonUiCss');
				if (uiCss) uiCss.href = 'css/mikhmon-ui.' + actual + '.min.css?v=3.20.2';
				var paceCss = document.getElementById('mikhmonPaceCss');
				if (paceCss) paceCss.href = 'css/pace.' + actual + '.css';
				var selects = document.querySelectorAll('.stheme, .stheme-mobile');
				selects.forEach(function(s) { s.value = actual; });
			} catch(e) {}

			try {
				if (typeof notify === 'function') notify('<?= $_loading_theme ?? "Loading theme..."; ?>');
			} catch(e) {}

			// Background session update & clean refresh
			fetch('./settings/settheme.php?set-theme=' + encodeURIComponent(actual) + '&ajax=1', {
				credentials: 'same-origin',
				cache: 'no-store'
			}).then(function() {
				var url = new URL(window.location.href);
				url.searchParams.delete('set-theme');
				url.searchParams.set('_t', Date.now());
				window.location.replace(url.toString());
			}).catch(function() {
				var url = new URL(window.location.href);
				url.searchParams.set('set-theme', actual);
				url.searchParams.set('_t', Date.now());
				window.location.replace(url.toString());
			});
		};

		window.mikhmonSwitchLang = function(lang) {
			if (!lang || window._mikhmonSwitching) return;
			var match = String(lang).match(/setlang=([^&]+)/);
			var actual = match ? match[1] : lang;
			if (!actual) return;
			window._mikhmonSwitching = true;

			try {
				document.cookie = 'mikhmon_lang=' + encodeURIComponent(actual) + '; path=/; max-age=31536000; SameSite=Lax';
				var selects = document.querySelectorAll('.slang, .slang-mobile');
				selects.forEach(function(s) { s.value = actual; });
			} catch(e) {}

			try {
				if (typeof notify === 'function') notify('<?= $_loading ?? "Loading..."; ?>');
			} catch(e) {}

			var navUrl = new URL(window.location.href);
			navUrl.searchParams.set('setlang', actual);
			navUrl.searchParams.set('_t', Date.now());

			try {
				fetch('./settings/setlang.php?setlang=' + encodeURIComponent(actual) + '&ajax=1', {
					credentials: 'same-origin',
					cache: 'no-store'
				}).finally(function() {
					window.location.replace(navUrl.toString());
				});
			} catch(e) {
				window.location.replace(navUrl.toString());
			}
		};

		window.deferredPwaPrompt = null;
		window.addEventListener('beforeinstallprompt', function(e) {
			e.preventDefault();
			window.deferredPwaPrompt = e;
			var btn = document.getElementById('btnInstallPwa');
			if (btn) {
				btn.style.display = 'flex';
			}
		});
		if ('serviceWorker' in navigator) {
			if ('caches' in window) {
				caches.keys().then(function(keys) {
					keys.forEach(function(key) {
						if (key !== 'mikhmon-pwa-v12') {
							caches.delete(key);
						}
					});
				});
			}

			window.addEventListener('load', function() {
				navigator.serviceWorker.register('./sw.js?v=12', { scope: './', updateViaCache: 'none' }).then(function(reg) {
					try { reg.update(); } catch(e) {}
					if (reg.waiting) {
						reg.waiting.postMessage({ action: 'skipWaiting' });
					}
					reg.onupdatefound = function() {
						var worker = reg.installing;
						if (worker) {
							worker.onstatechange = function() {
								if (worker.state === 'installed' && navigator.serviceWorker.controller) {
									window.location.reload();
								}
							};
						}
					};
					console.log('Mikhmon SW registered:', reg.scope);
				}).catch(function(err) {
					console.log('Mikhmon SW error:', err);
				});

				var refreshing = false;
				navigator.serviceWorker.addEventListener('controllerchange', function() {
					if (!refreshing) {
						refreshing = true;
						window.location.reload();
					}
				});
			});
		}
		</script>
			
			<style>
		/* Sidebar Fixed & Scrollable Viewport Fix */
		#sidenav, .sidenav,
		body.sidebar-open #sidenav,
		body.sidebar-open .sidenav {
			height: calc(100vh - 51px) !important;
			max-height: calc(100vh - 51px) !important;
			top: 51px !important;
			margin-top: 0 !important;
			overflow-y: auto !important;
			overflow-x: hidden !important;
			overscroll-behavior: contain !important;
			touch-action: pan-y !important;
			-webkit-overflow-scrolling: touch !important;
			box-sizing: border-box !important;
			padding-bottom: 90px !important;
		}
		#sidenav::-webkit-scrollbar, .sidenav::-webkit-scrollbar {
			width: 6px;
		}
		#sidenav::-webkit-scrollbar-thumb, .sidenav::-webkit-scrollbar-thumb {
			background: rgba(128, 128, 128, 0.4);
			border-radius: 3px;
		}
		.sidenav a, .sidenav .dropdown-btn {
			white-space: normal !important;
			word-break: break-word !important;
			display: flex !important;
			align-items: center !important;
			justify-content: flex-start !important;
			box-sizing: border-box !important;
			width: calc(100% - 10px) !important;
			padding: 6px 8px !important;
			min-height: 32px !important;
		}
		.sidenav a i, .sidenav .dropdown-btn i {
			flex-shrink: 0 !important;
			width: 20px !important;
			text-align: center !important;
			margin-right: 8px !important;
			padding: 0 !important;
			font-size: 15px !important;
		}
		.sidenav a span, .sidenav a b, .sidenav .dropdown-btn b {
			flex: 1 !important;
			line-height: 1.3 !important;
		}
		.sidenav .dropdown-btn .fa-caret-down {
			margin-left: auto !important;
			margin-right: 4px !important;
			font-size: 13px !important;
		}
		.dropdown-container a {
			padding-left: 28px !important;
		}

		/* Topnav page title: hide on desktop, only display on mobile */
		#cpage, .navbar #cpage, .navbar-left #cpage {
			display: none !important;
		}

		/* Mobile Drawer Scroll Fix */
		@media screen and (max-width: 768px) {
			#cpage, .navbar #cpage, .navbar-left #cpage {
				display: flex !important;
			}
			body.sidebar-open #sidenav,
			body.sidebar-open .sidenav,
			#sidenav[style*="width: 2"],
			#sidenav[style*="display: block"] {
				height: 100vh !important;
				max-height: 100vh !important;
				top: 0 !important;
				overflow-y: auto !important;
				overflow-x: hidden !important;
				overscroll-behavior: contain !important;
				touch-action: pan-y !important;
				-webkit-overflow-scrolling: touch !important;
				padding-bottom: 120px !important;
			}
		}

		/* Sidebar Closed State - only hide when NOT active/open */
		body.sidebar-closed #sidenav:not([style*="display: block"]):not([style*="width: 2"]):not([style*="width: 270px"]),
		body.sidebar-closed .sidenav:not([style*="display: block"]):not([style*="width: 2"]):not([style*="width: 270px"]) {
			width: 0 !important;
			border-right: none !important;
			overflow: hidden !important;
		}
		body.sidebar-closed #main {
			margin-left: 0 !important;
			width: 100% !important;
			max-width: 100% !important;
		}
		body.sidebar-closed #brand {
			display: none !important;
		}
		body.sidebar-closed #closeNav {
			display: none !important;
		}
		body.sidebar-closed #openNav {
			display: block !important;
		}

		/* Sidebar Open State */
		body.sidebar-open #sidenav,
		body.sidebar-open .sidenav {
			width: 210px !important;
			border-right: 1px solid #23282c !important;
		}
		@media screen and (min-width: 800px) {
			body.sidebar-open #main {
				margin-left: 210px !important;
				width: calc(100% - 210px) !important;
				max-width: calc(100% - 210px) !important;
			}
		}
		body.sidebar-open #brand {
			display: block !important;
		}
		body.sidebar-open #closeNav {
			display: block !important;
		}
		body.sidebar-open #openNav {
			display: none !important;
		}

		/* Dropdown container styling */
		.dropdown-container {
			display: none;
		}
		.dropdown-container.menu-open {
			display: block !important;
		}
		.dropdown-btn {
			cursor: pointer;
			user-select: none;
		}
		.dropdown-btn .fa-caret-down {
			transition: transform 0.2s ease;
		}
		.dropdown-btn.active .fa-caret-down {
			transform: rotate(180deg);
		}
		.text-green {
			color: #4dbd74 !important;
		}
		.text-yellow {
			color: #ffc107 !important;
		}
		/* Fix global input margin causing checkboxes/radios to be centered */
		input[type="checkbox"], input[type="radio"] {
			margin: 0 !important;
			vertical-align: middle !important;
		}
		/* Card Header Responsive Flex Layout & Mobile Actions */
		.card-header-flex {
			display: flex;
			align-items: center;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 6px 12px;
			width: 100%;
			box-sizing: border-box;
		}
		.card-header-title {
			margin: 0 !important;
			font-size: 16px !important;
			font-weight: 600;
			display: inline-flex;
			align-items: center;
			flex-wrap: wrap;
			gap: 6px;
			line-height: 1.35;
			color: inherit;
		}
		.card-header-actions {
			display: inline-flex;
			align-items: center;
			flex-wrap: wrap;
			gap: 6px;
			font-size: 13px;
		}
		.header-action-btn {
			display: inline-flex;
			align-items: center;
			gap: 5px;
			padding: 4px 9px;
			font-size: 12.5px;
			font-weight: 500;
			line-height: 1.3;
			border-radius: 4px;
			text-decoration: none !important;
			background: rgba(128, 128, 128, 0.12);
			color: inherit !important;
			border: 1px solid rgba(128, 128, 128, 0.22);
			transition: background-color 0.15s ease, border-color 0.15s ease;
			white-space: nowrap;
			cursor: pointer;
		}
		.header-action-btn:hover {
			background: rgba(128, 128, 128, 0.25);
			border-color: rgba(128, 128, 128, 0.45);
			color: inherit !important;
		}
		.header-action-btn.active {
			background: rgba(32, 168, 216, 0.22);
			border-color: #20a8d8;
			color: #20a8d8 !important;
			font-weight: 600;
		}
		.header-action-btn.btn-text-info {
			color: #20a8d8 !important;
		}
		.header-action-btn.btn-text-success {
			color: #4dbd74 !important;
		}
		.header-action-btn.btn-text-primary {
			color: #20a8d8 !important;
		}
		@media screen and (max-width: 750px) {
			.card-header-flex {
				flex-direction: column;
				align-items: flex-start;
				gap: 8px;
			}
			.card-header-title {
				width: 100%;
			}
			.card-header-actions {
				width: 100%;
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: 6px;
			}
			.header-action-btn {
				padding: 4px 8px;
				font-size: 12px;
			}
		}
		/* Mikhmon Loading & Blocking Overlay Styles - Transparent Black with Subtle Blur */
		#mikhmon-loading-overlay {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			width: 100vw;
			height: 100vh;
			z-index: 2147483647;
			background: rgba(0, 0, 0, 0.72);
			backdrop-filter: blur(4px);
			-webkit-backdrop-filter: blur(4px);
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			pointer-events: all;
			user-select: none;
			-webkit-user-select: none;
			touch-action: none;
			transition: opacity 0.25s ease;
		}
		.mikhmon-overlay-box {
			background: transparent;
			border: none;
			box-shadow: none;
			padding: 0;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			max-width: 90vw;
			text-align: center;
		}
		.mikhmon-overlay-spinner-wrap {
			position: relative;
			width: 50px;
			height: 50px;
			margin-bottom: 16px;
		}
		.mikhmon-overlay-spinner-track {
			position: absolute;
			inset: 0;
			border: 4px solid rgba(255, 255, 255, 0.15);
			border-radius: 50%;
		}
		.mikhmon-overlay-spinner {
			position: absolute;
			inset: 0;
			border: 4px solid transparent;
			border-top-color: #38bdf8;
			border-right-color: #38bdf8;
			border-radius: 50%;
			animation: mikhmonOverlaySpin 0.75s linear infinite;
			filter: drop-shadow(0 0 8px rgba(56, 189, 248, 0.6));
		}
		.mikhmon-overlay-title {
			color: #ffffff;
			font-size: 16px;
			font-weight: 700;
			letter-spacing: 0.2px;
			margin-bottom: 6px;
			text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);
		}
		.mikhmon-overlay-desc {
			color: #cbd5e1;
			font-size: 13px;
			line-height: 1.4;
			text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
		}
		.mikhmon-overlay-dots {
			display: flex;
			gap: 6px;
			margin-top: 14px;
		}
		.mikhmon-overlay-dots span {
			width: 6.5px;
			height: 6.5px;
			background: #38bdf8;
			border-radius: 50%;
			box-shadow: 0 0 6px rgba(56, 189, 248, 0.8);
			animation: mikhmonDotPulse 1.2s infinite ease-in-out both;
		}
		.mikhmon-overlay-dots span:nth-child(2) {
			animation-delay: 0.2s;
		}
		.mikhmon-overlay-dots span:nth-child(3) {
			animation-delay: 0.4s;
		}
		@keyframes mikhmonOverlaySpin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}
		@keyframes mikhmonDotPulse {
			0%, 80%, 100% { transform: scale(0.6); opacity: 0.35; }
			40% { transform: scale(1.15); opacity: 1; }
		}
		</style>
		<?php
		include_once __DIR__ . '/license.php';
		if (function_exists('mikhmon_render_desktop_heartbeat_script')) {
			mikhmon_render_desktop_heartbeat_script();
		}
		?>
	
		<script>
		(function() {
			function initSidenavScrollCapture() {
				var s = document.getElementById('sidenav');
				if (!s || s._wheelBound) return;
				s._wheelBound = true;
				s.addEventListener('wheel', function(e) {
					if (s.scrollHeight > s.clientHeight) {
						var prevScroll = s.scrollTop;
						s.scrollTop += e.deltaY;
						if (s.scrollTop !== prevScroll || (e.deltaY > 0 && s.scrollTop < s.scrollHeight - s.clientHeight) || (e.deltaY < 0 && s.scrollTop > 0)) {
							e.preventDefault();
							e.stopPropagation();
						}
					}
				}, { passive: false });
			}
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', initSidenavScrollCapture);
			} else {
				initSidenavScrollCapture();
			}
			window.addEventListener('load', initSidenavScrollCapture);
		})();
		</script>

	</head>
	<body>
		<!-- Mikhmon Loading & Interaction Blocking Overlay -->
		<div id="mikhmon-loading-overlay" style="display: none; opacity: 0;">
			<div class="mikhmon-overlay-box">
				<div class="mikhmon-overlay-spinner-wrap">
					<div class="mikhmon-overlay-spinner-track"></div>
					<div class="mikhmon-overlay-spinner"></div>
				</div>
				<div id="mikhmon-overlay-title" class="mikhmon-overlay-title">Loading...</div>
				<div id="mikhmon-overlay-desc" class="mikhmon-overlay-desc" style="display:none;"></div>
				<div class="mikhmon-overlay-dots">
					<span></span>
					<span></span>
					<span></span>
				</div>
			</div>
		</div>
		<script>
		if (sessionStorage.getItem('mikhmon_switching') === '1') {
			try { sessionStorage.removeItem('mikhmon_switching'); } catch(e) {}
			var _ov = document.getElementById('mikhmon-loading-overlay');
			if (_ov) {
				_ov.style.display = 'flex';
				_ov.style.opacity = '1';
			}
			setTimeout(function() {
				if (typeof window.hideMikhmonOverlay === "function") window.hideMikhmonOverlay();
			}, 700);
		}
		window._mikhmonOverlayTimer = null;
		window.showMikhmonOverlay = function(title, desc) {
			try { sessionStorage.setItem('mikhmon_switching', '1'); } catch(e) {}
			var ov = document.getElementById('mikhmon-loading-overlay');
			if (ov) {
				var t = document.getElementById('mikhmon-overlay-title');
				if (t) t.innerText = title || "Loading...";
				var d = document.getElementById('mikhmon-overlay-desc');
				if (d) {
					if (desc) {
						d.innerText = desc;
						d.style.display = 'block';
					} else {
						d.style.display = 'none';
					}
				}
				ov.style.display = 'flex';
				setTimeout(function() { ov.style.opacity = '1'; }, 10);
			}
			if (window._mikhmonOverlayTimer) clearTimeout(window._mikhmonOverlayTimer);
			window._mikhmonOverlayTimer = setTimeout(function() {
				window.hideMikhmonOverlay();
			}, 2000);
		};
		window.hideMikhmonOverlay = function() {
			if (window._mikhmonOverlayTimer) {
				clearTimeout(window._mikhmonOverlayTimer);
				window._mikhmonOverlayTimer = null;
			}
			try { sessionStorage.removeItem('mikhmon_switching'); } catch(e) {}
			var ov = document.getElementById('mikhmon-loading-overlay');
			if (ov) {
				ov.style.opacity = '0';
				setTimeout(function() {
					if (ov.style.opacity === '0') {
						ov.style.display = 'none';
					}
				}, 200);
			}
		};
		<?php
		$isDashboardPage = (isset($hotspot) && $hotspot == "dashboard") || (!empty($_GET['session']) && empty($_GET['hotspot']) && empty($_GET['page']) && empty($id));
		?>
		window.mikhmonIsDashboard = <?= $isDashboardPage ? 'true' : 'false'; ?>;
		window.mikhmonR3Done = !window.mikhmonIsDashboard;
		window.mikhmonWindowLoaded = false;
		window.mikhmonPaceDone = false;

		window.registerMikhmonInitialLoad = function() {
			window.mikhmonR3Done = false;
		};

		window.completeMikhmonInitialLoad = function() {
			window.mikhmonR3Done = true;
			_checkMikhmonReadyToDismiss();
		};

		function _checkMikhmonReadyToDismiss() {
			if (window.mikhmonIsDashboard && !window.mikhmonR3Done) {
				return;
			}

			if (window.Pace && typeof window.Pace.running === 'boolean' && window.Pace.running) {
				return;
			}

			if (window.mikhmonPaceDone || window.mikhmonWindowLoaded) {
				setTimeout(function() {
					window.hideMikhmonOverlay();
				}, 150);
			}
		}
		if (window.Pace) {
			window.Pace.on('done', function() {
				window.mikhmonPaceDone = true;
				_checkMikhmonReadyToDismiss();
			});
			window.Pace.on('hide', function() {
				window.mikhmonPaceDone = true;
				_checkMikhmonReadyToDismiss();
			});
		}
		window.addEventListener('load', function() {
			window.mikhmonWindowLoaded = true;
			_checkMikhmonReadyToDismiss();
			setTimeout(function() { window.hideMikhmonOverlay(); }, 400);
		});
		window.addEventListener('pageshow', function() {
			window.hideMikhmonOverlay();
		});
		$(function() {
			setTimeout(function() {
				if (!window.mikhmonIsDashboard || window.mikhmonR3Done) {
					window.hideMikhmonOverlay();
				}
			}, 300);
		});
		setTimeout(function() {
			window.hideMikhmonOverlay();
		}, 1200);
		</script>
		<div class="wrapper">
<?php
if (file_exists(__DIR__ . '/bottomnav.php')) {
    include_once __DIR__ . '/bottomnav.php';
}
?>
