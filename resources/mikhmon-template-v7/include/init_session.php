<?php
/**
 * NODERA MIKHMON - Unified Multi-Tenant Session Initializer
 * Ensures consistent session naming (MIKH_<hash>) and cookie path across all endpoints
 * (root, admin, process, hotspot, status, voucher, dashboard, report, settings, etc.)
 */
if (!function_exists('mikhmon_init_session')) {
    function mikhmon_init_session() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Mikhmon root directory is always the parent directory of include/
        $includeDir = __DIR__;
        $mikhRoot = dirname($includeDir);
        $instanceHash = substr(md5($mikhRoot), 0, 10);
        @session_name('MIKH_' . $instanceHash);

        // Calculate base cookie path relative to web root
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = rtrim(dirname($scriptName), '/');
        $subdirs = ['/process', '/status', '/hotspot', '/voucher', '/dashboard', '/report', '/system', '/settings', '/traffic', '/dhcp', '/include', '/bandwidth'];
        $baseCookiePath = $scriptDir;
        foreach ($subdirs as $sd) {
            if (str_ends_with($baseCookiePath, $sd)) {
                $baseCookiePath = substr($baseCookiePath, 0, -strlen($sd));
                break;
            }
        }
        $cookiePath = !empty($baseCookiePath) ? $baseCookiePath : '/';

        @ini_set('session.cookie_path', $cookiePath);
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
}

mikhmon_init_session();
