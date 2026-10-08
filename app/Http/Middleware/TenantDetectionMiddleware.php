<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class TenantDetectionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->attributes->has('_tenant_detection_done')) {
            return $next($request);
        }
        $request->attributes->set('_tenant_detection_done', true);

        // Superadmin routes should never be trapped in tenant context
        if ($request->is('superadmin*') || $request->is('api/superadmin*') || $request->is('nodera/superadmin*')) {
            $request->attributes->set('tenant_id', null);
            return $next($request);
        }

        // ── Impersonasi superadmin → admin tenant ──
        if (session('impersonating') && session('tenant_id')) {
            $request->attributes->set('tenant_id', (int) session('tenant_id'));
            $request->attributes->set('tenant_slug', session('tenant_slug'));
            $request->attributes->set('tenant_name', session('tenant_name'));
            view()->share('currentTenant', Tenant::find(session('tenant_id')));
            return $next($request);
        }

        $host = $request->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST));

        // 301 Permanent Redirect www.* to non-www for SEO Canonical unification
        if (str_starts_with(strtolower($host), 'www.')) {
            $targetHost = substr($host, 4);
            $targetUrl = $request->getScheme() . '://' . $targetHost . $request->getRequestUri();
            return redirect()->to($targetUrl, 301);
        }

        // Webhooks & direct deploy endpoints always run without tenant context
        if ($request->is('webhook*') || $request->is('api/webhook*') || in_array($request->path(), ['deploy.php', 'deploy-status.json'])) {
            $request->attributes->set('tenant_id', null);
            session()->forget(['tenant_id', 'tenant_slug', 'tenant_name']);
            return $next($request);
        }

        // Extract potential subdomain
        $parts = explode('.', $host);
        $subdomain = $parts[0] ?? null;

        // Dedicated transparent reverse proxy for ATH Trading Terminal
        if ($subdomain === 'ath') {
            $targetUrl = 'http://113.192.48.45:666' . $request->getRequestUri();
            
            $ch = curl_init($targetUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->method());
            if ($request->isMethod('HEAD')) {
                curl_setopt($ch, CURLOPT_NOBODY, true);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            $headers = [];
            foreach ($request->headers->all() as $name => $values) {
                if (in_array(strtolower($name), ['host', 'connection', 'content-length'])) continue;
                foreach ($values as $val) {
                    $headers[] = "$name: $val";
                }
            }
            $headers[] = 'Host: 113.192.48.45:666';
            $headers[] = 'X-Forwarded-For: ' . $request->ip();
            $headers[] = 'X-Forwarded-Proto: https';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request->getContent());
            }
            
            $rawResponse = curl_exec($ch);
            if ($rawResponse === false) {
                $err = curl_error($ch);
                curl_close($ch);
                return response("ATH Proxy Error: Unable to connect to trading terminal at $targetUrl ($err).", 502);
            }
            
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $rawHeaders = substr($rawResponse, 0, $headerSize);
            $body = substr($rawResponse, $headerSize);
            
            $res = response($body, $httpCode);
            foreach (explode("\r\n", $rawHeaders) as $headerLine) {
                if (str_contains($headerLine, ':')) {
                    [$k, $v] = explode(':', $headerLine, 2);
                    $k = trim($k);
                    $v = trim($v);
                    if (!in_array(strtolower($k), ['transfer-encoding', 'content-encoding', 'connection'])) {
                        if (strtolower($k) === 'location') {
                            $v = str_replace('http://113.192.48.45:666', '', $v);
                            if (empty($v)) $v = '/';
                        }
                        $res->header($k, $v);
                    }
                }
            }
            return $res;
        }

        // Extract potential path segment for subpath-based standalone app access (e.g. localhost:8000/mikhmon-sample/ or 103.x.x.x/mikhmon-sample/)
        $pathInfo = ltrim($request->getPathInfo(), '/');
        $pathSegments = explode('/', $pathInfo);
        $firstPathSegment = $pathSegments[0] ?? '';

        $standaloneFolder = null;
        $relativeUri = '';

        // 1. Check if accessed via subdomain: mikhmon-* or hotspot-* or kas-* or folder in public/
        if ($subdomain && $subdomain !== 'www' && $subdomain !== 'panel' && $subdomain !== 'gateway' && $subdomain !== 'wa' && $subdomain !== 'wagateway' && $subdomain !== 'shop' && (
            str_starts_with($subdomain, 'mikhmon-') ||
            str_starts_with($subdomain, 'hotspot-') ||
            str_starts_with($subdomain, 'kas-') ||
            str_starts_with($subdomain, 'mikhmon_') ||
            str_starts_with($subdomain, 'hotspot_') ||
            str_starts_with($subdomain, 'kas_') ||
            (is_dir(public_path($subdomain)) && !in_array($subdomain, ['assets', 'build', 'css', 'js', 'images', 'uploads', 'storage', 'downloads', 'mazer-assets', 'brivon-assets', 'titan', 'img']))
        )) {
            $standaloneFolder = $subdomain;
            $relativeUri = $pathInfo;
        }
        // 2. Check if accessed via path: /mikhmon-*, /hotspot-*, or /kas-*
        elseif (!empty($firstPathSegment) && $firstPathSegment !== 'mikhmon-online' && (str_starts_with($firstPathSegment, 'mikhmon-') || str_starts_with($firstPathSegment, 'hotspot-') || str_starts_with($firstPathSegment, 'kas-') || str_starts_with($firstPathSegment, 'mikhmon_') || str_starts_with($firstPathSegment, 'hotspot_') || str_starts_with($firstPathSegment, 'kas_'))) {
            $standaloneFolder = $firstPathSegment;
            $relativeUri = implode('/', array_slice($pathSegments, 1));

            // Redirect /hotspot-xxx to /hotspot-xxx/ for proper relative links resolution in browser
            if (empty($relativeUri) && !str_ends_with($request->getPathInfo(), '/')) {
                $qs = $request->getQueryString();
                return redirect('/' . $firstPathSegment . '/' . ($qs ? '?' . $qs : ''), 301);
            }
        }

        // 3. Fallback: Check if request is an orphaned asset/page from a mikhmon/kas referer or direct standalone script
        if (!$standaloneFolder) {
            $isHotspotDnsSubdomain = in_array($subdomain, ['net', 'hotspot', 'wifi', 'portal', 'voucher']);
            $isBaseDomain = (!$subdomain || $subdomain === 'www' || $subdomain === 'panel' || $subdomain === 'gateway' || $subdomain === 'wa' || $subdomain === 'wagateway' || $subdomain === 'shop' || $host === $baseDomain || in_array($host, ['dgtlnetsolution.com', 'airnetsolution.com', 'nodera.id', 'localhost', '127.0.0.1', '127.0.0.1']));

            $laravelCoreRoutes = [
                'login', 'register', 'logout', 'dashboard', 'admin', 'superadmin',
                'teknisi', 'kolektor', 'pelanggan', 'api', 'up', 'vpn', 'topup',
                'invoices', 'customers', 'settings', 'profile', 'my-settings', 'pay',
                'checkout', 'orders', 'packages', 'users', 'finance', 'reports',
                'inventory', 'attendance', 'payroll', 'announcements', 'arisan',
                'genieacs', 'olt', 'radius', 'mikrotik', 'docs', 'panduan', 'guide',
                'tools', 'software-billing-isp', 'billing-rt-rw-net', 'billing-mikrotik',
                'monitoring-mikrotik', 'nms-olt', 'gis-fiber-optic', 'mikhmon-online',
                'harga', 'pricing', 'blog', 'terms', 'privacy', 'downloads', 'noderapay', 'gateway',
                'wagateway', 'wa', 'devices', 'messages', 'credentials', 'billing',
                'quick-send', 'media', 'broadcast', 'scheduled', 'auto-reply', 'queue', 'activity-logs'
            ];

            // If accessing Laravel core routes on base domain, skip standalone hijacking completely
            if ($isBaseDomain && in_array($firstPathSegment, $laravelCoreRoutes)) {
                $isStandaloneCandidate = false;
            } else {
                $knownStandaloneFiles = [
                    'admin.php', 'check_admin.php', 'install.php', 'update.php', 
                    'setup_admin_whatsapp.php', 'admin_payment_methods.php',
                    'telegram_webhook.php', 'api_webhook.php', 'buy_process.php', 'buy.php',
                    'buy', 'beli', 'voucher', 'status'
                ];
                if ($isHotspotDnsSubdomain) {
                    $knownStandaloneFiles[] = 'login';
                    $knownStandaloneFiles[] = 'admin';
                    $knownStandaloneFiles[] = 'logout';
                }
                $isStandaloneCandidate = in_array($firstPathSegment, $knownStandaloneFiles) || in_array($pathInfo, $knownStandaloneFiles) || $isHotspotDnsSubdomain;
            }

            $resolvedFolder = null;

            // 3a. From Referer Header
            if (!empty($_SERVER['HTTP_REFERER'])) {
                $refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?? '';
                $refSegments = explode('/', ltrim($refPath, '/'));
                $refFolder = $refSegments[0] ?? '';
                if (str_starts_with($refFolder, 'mikhmon-') || str_starts_with($refFolder, 'hotspot-') || str_starts_with($refFolder, 'kas-')) {
                    $resolvedFolder = $refFolder;
                }
            }

            // 3b. From Session / Cookie
            if (!$resolvedFolder && $isStandaloneCandidate) {
                $sessFolder = session('last_standalone_folder') ?? $request->cookie('last_standalone_folder');
                if ($sessFolder && (str_starts_with($sessFolder, 'mikhmon-') || str_starts_with($sessFolder, 'hotspot-') || str_starts_with($sessFolder, 'kas-'))) {
                    $resolvedFolder = $sessFolder;
                }
            }

            // 3c. From authenticated VPN User's Mikhmon subscription
            if (!$resolvedFolder && $isStandaloneCandidate) {
                try {
                    $vpnUser = \Illuminate\Support\Facades\Auth::guard('vpn')->user();
                    if ($vpnUser) {
                        $userSub = \App\Models\MikhmonSubscription::where('vpn_user_id', $vpnUser->id)->latest()->first();
                        if ($userSub) {
                            $resolvedFolder = $userSub->subdomain;
                        }
                    }
                } catch (\Throwable $e) {}
            }

            // 3d. From latest active Mikhmon subscription in database
            if (!$resolvedFolder && $isStandaloneCandidate) {
                try {
                    $activeSub = \App\Models\MikhmonSubscription::where('status', 'ACTIVE')->latest()->first();
                    if ($activeSub) {
                        $resolvedFolder = $activeSub->subdomain;
                    }
                } catch (\Throwable $e) {}
            }

            // 3e. For Webhook POSTs (telegram_webhook.php / api_webhook.php), detect folder from query params or payload
            if (!$resolvedFolder && (str_contains($pathInfo, 'telegram_webhook.php') || str_contains($pathInfo, 'api_webhook.php'))) {
                // Check query params ?instance=... or ?subdomain=... or ?folder=...
                $qFolder = $request->query('instance') ?: ($request->query('subdomain') ?: $request->query('folder'));
                if (!empty($qFolder)) {
                    $resolvedFolder = trim($qFolder);
                }

                if (!$resolvedFolder) {
                    $rawBody = @file_get_contents('php://input');
                    if (empty($rawBody)) {
                        $rawBody = $request->getContent();
                    }
                    if (!empty($rawBody)) {
                        $jsonPayload = @json_decode($rawBody, true);
                        $cbData = $jsonPayload['callback_query']['data'] ?? '';
                        $orderId = '';
                        if (str_starts_with($cbData, 'acc_vch:') || str_starts_with($cbData, 'rej_vch:')) {
                            $orderId = trim(substr($cbData, 8));
                        } elseif (str_starts_with($cbData, 'acc_') || str_starts_with($cbData, 'rej_') || str_starts_with($cbData, 'acc:') || str_starts_with($cbData, 'rej:')) {
                            $orderId = trim(substr($cbData, 4));
                        } elseif (!empty($jsonPayload['order_id'])) {
                            $orderId = trim($jsonPayload['order_id']);
                        }

                        if (!empty($orderId)) {
                            $scanRoots = array_unique(array_filter([
                                public_path(),
                                base_path(),
                                base_path('public'),
                                config('mikhmon.base_path') ? rtrim(config('mikhmon.base_path'), '/') : null,
                            ]));
                            foreach ($scanRoots as $sRoot) {
                                if (!is_dir($sRoot)) continue;
                                $dirs = glob($sRoot . '/mikhmon-*') ?: [];
                                $dirs = array_merge($dirs, glob($sRoot . '/hotspot-*') ?: []);
                                $dirs = array_merge($dirs, glob($sRoot . '/kas-*') ?: []);
                                if (is_dir($sRoot . '/hotspot-themes')) {
                                    $dirs[] = $sRoot . '/hotspot-themes';
                                }
                                foreach ($dirs as $d) {
                                    $ordFile = $d . '/include/orders_data.json';
                                    if (file_exists($ordFile)) {
                                        $ords = @json_decode(@file_get_contents($ordFile), true);
                                        if (!empty($ords[$orderId])) {
                                            $resolvedFolder = basename($d);
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($resolvedFolder) {
                $candidate = public_path($resolvedFolder . '/' . $pathInfo);
                // POST requests & webhooks MUST NEVER be 302-redirected (POST payload would be dropped)
                if ($request->isMethod('POST') || str_contains($pathInfo, 'webhook') || str_contains($pathInfo, 'process')) {
                    $standaloneFolder = $resolvedFolder;
                    $relativeUri = $pathInfo;
                } elseif (file_exists($candidate) || file_exists($candidate . '.php') || $isStandaloneCandidate) {
                    $qs = $request->getQueryString();
                    return redirect('/' . $resolvedFolder . '/' . $pathInfo . ($qs ? '?' . $qs : ''), 302);
                }
            }

            // Fallback for hotspot DNS subdomains or standalone themes (STRICT: Never pick another tenant's directory)
            if (!$resolvedFolder && ($isStandaloneCandidate || in_array($subdomain, ['net', 'hotspot', 'wifi', 'login', 'portal', 'voucher']))) {
                if (is_dir(public_path('hotspot-themes'))) {
                    $resolvedFolder = 'hotspot-themes';
                }
            }
        }

        // ── Standalone Application Execution (Mikhmon, Pembukuan, etc.) ──
        if ($standaloneFolder) {
            session(['last_standalone_folder' => $standaloneFolder]);

            $isKas = str_starts_with($standaloneFolder, 'kas-') || str_starts_with($standaloneFolder, 'kas_');
            $isMikhmon = str_starts_with($standaloneFolder, 'mikhmon-') || str_starts_with($standaloneFolder, 'hotspot-') || str_starts_with($standaloneFolder, 'mikhmon_') || str_starts_with($standaloneFolder, 'hotspot_');

            // 1. Verify subscription in database
            $subscription = null;
            $serviceType = $isKas ? 'kas' : ($isMikhmon ? 'mikhmon' : null);
            $cleanName = null;

            if ($isKas) {
                $cleanName = preg_replace('/^kas[-_]/', '', $standaloneFolder);
                $searchTerms = array_unique(array_filter([
                    $standaloneFolder,
                    $cleanName,
                    'kas-' . $cleanName,
                    'kas_' . $cleanName,
                    str_replace('-', '_', $standaloneFolder),
                    str_replace('_', '-', $standaloneFolder),
                    str_replace('-', '_', $cleanName),
                    str_replace('_', '-', $cleanName),
                    str_replace(['-', '_', ' '], '', $cleanName),
                ]));

                try {
                    $subscription = \App\Models\BookkeepingSubscription::where(function ($query) use ($searchTerms, $cleanName) {
                        $query->whereIn('subdomain', $searchTerms);
                        foreach ($searchTerms as $term) {
                            $query->orWhereRaw('LOWER(TRIM(subdomain)) = ?', [strtolower(trim($term))]);
                        }
                        $cleanAlphanum = str_replace(['-', '_', ' '], '', strtolower($cleanName));
                        if (!empty($cleanAlphanum)) {
                            $query->orWhereRaw('REPLACE(REPLACE(LOWER(TRIM(subdomain)), "kas-", ""), "-", "") = ?', [$cleanAlphanum]);
                        }
                    })->first();
                } catch (\Throwable $e) {}
            } elseif ($isMikhmon) {
                $cleanName = preg_replace('/^(mikhmon|hotspot)[-_]/', '', $standaloneFolder);
                $searchTerms = array_unique(array_filter([
                    $standaloneFolder,
                    $cleanName,
                    'hotspot-' . $cleanName,
                    'hotspot_' . $cleanName,
                    'mikhmon-' . $cleanName,
                    'mikhmon_' . $cleanName,
                    str_replace('-', '_', $standaloneFolder),
                    str_replace('_', '-', $standaloneFolder),
                    str_replace('-', '_', $cleanName),
                    str_replace('_', '-', $cleanName),
                    str_replace(['-', '_', ' '], '', $cleanName),
                ]));

                try {
                    $subscription = \App\Models\MikhmonSubscription::where(function ($query) use ($searchTerms, $cleanName) {
                        $query->whereIn('subdomain', $searchTerms);
                        foreach ($searchTerms as $term) {
                            $query->orWhereRaw('LOWER(TRIM(subdomain)) = ?', [strtolower(trim($term))]);
                        }
                        $cleanAlphanum = str_replace(['-', '_', ' '], '', strtolower($cleanName));
                        if (!empty($cleanAlphanum)) {
                            $query->orWhereRaw('REPLACE(REPLACE(REPLACE(LOWER(TRIM(subdomain)), "hotspot-", ""), "mikhmon-", ""), "-", "") = ?', [$cleanAlphanum]);
                        }
                    })->first();

                    if (!$subscription && !empty($cleanName)) {
                        $tenantCandidate = \App\Models\Tenant::where('slug', $cleanName)
                            ->orWhere('slug', str_replace(['mikhmon-', 'hotspot-'], '', $cleanName))
                            ->first();
                        if ($tenantCandidate) {
                            $subscription = \App\Models\MikhmonSubscription::where('tenant_id', $tenantCandidate->id)->first();
                        }
                    }
                } catch (\Throwable $e) {}

                // Fallback: If subscription not yet in DB, check if Tenant exists with active Mikhmon and auto-sync!
                if (!$subscription && !empty($cleanName)) {
                    try {
                        $tenantCandidate = \App\Models\Tenant::where('slug', $cleanName)
                            ->orWhere('slug', $standaloneFolder)
                            ->orWhere('slug', str_replace(['mikhmon-', 'hotspot-'], '', $standaloneFolder))
                            ->first();

                        if ($tenantCandidate) {
                            $mikhmonAddon = \App\Models\Addon::whereIn('slug', ['mikhmon_online', 'mikhmon'])->where('is_active', true)->first();
                            $hasAccess = (new \App\Services\AddonService())->canAccess($tenantCandidate, 'mikhmon_online') || (new \App\Services\AddonService())->canAccess($tenantCandidate, 'mikhmon');
                            $tenantAddon = $mikhmonAddon ? \App\Models\TenantAddon::where('tenant_id', $tenantCandidate->id)->where('addon_id', $mikhmonAddon->id)->first() : null;

                            if ($hasAccess || $tenantCandidate->is_active) {
                                $subscription = \App\Services\MikhmonProvisioner::syncForTenant($tenantCandidate, $tenantAddon);
                            }
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('[TenantDetection] Auto-sync tenant Mikhmon error: ' . $e->getMessage());
                    }
                }
            }

            // Check status / expiration if subscription exists in DB
            if ($subscription) {
                $isExpired = false;
                $expiresAt = $subscription->expires_at ? \Carbon\Carbon::parse($subscription->expires_at) : null;
                $status = $subscription->status ?? 'ACTIVE';

                if (in_array($status, ['SUSPENDED', 'DISABLED', 'BLOCKED', 'EXPIRED'])) {
                    $isExpired = true;
                } elseif ($expiresAt && now()->startOfDay()->gt($expiresAt->endOfDay())) {
                    $isExpired = true;
                    $status = 'EXPIRED';
                }

                if ($isExpired) {
                    $mainDomain = $baseDomain ?: 'dgtlnetsolution.com';
                    $renewUrl = 'https://panel.' . $mainDomain . '/' . ($serviceType === 'kas' ? 'bookkeeping' : 'mikhmon');
                    $serviceTitle = ($serviceType === 'kas') ? 'Pembukuan Kas Online' : 'MIKHMON Online';
                    $expDateStr = $expiresAt ? $expiresAt->format('Y-m-d') : null;

                    return $this->renderExpiredOrInactivePage($serviceTitle, $standaloneFolder, $expDateStr, $status, $renewUrl);
                }
            }

            // Build all candidate folder names and base directories
            $candidateFolderNames = array_unique(array_filter([
                $standaloneFolder,
                $cleanName,
                'hotspot-' . $cleanName,
                'mikhmon-' . $cleanName,
                $subscription?->subdomain,
                $subscription ? 'kas-' . preg_replace('/^kas[-_]/', '', $subscription->subdomain) : null,
                $subscription ? 'hotspot-' . preg_replace('/^(mikhmon|hotspot)[-_]/', '', $subscription->subdomain) : null,
                $subscription ? 'mikhmon-' . preg_replace('/^(mikhmon|hotspot)[-_]/', '', $subscription->subdomain) : null,
                $subscription ? preg_replace('/^(kas|mikhmon|hotspot)[-_]/', '', $subscription->subdomain) : null,
                str_replace('-', '_', $standaloneFolder),
                str_replace('_', '-', $standaloneFolder),
                $cleanName ? str_replace('-', '_', $cleanName) : null,
                $cleanName ? str_replace('_', '-', $cleanName) : null,
            ]));

            $baseDirs = array_unique(array_filter([
                $serviceType === 'kas' && config('bookkeeping.base_path') ? rtrim(config('bookkeeping.base_path'), '/') : null,
                $serviceType === 'mikhmon' && config('mikhmon.base_path') ? rtrim(config('mikhmon.base_path'), '/') : null,
                public_path(),
                base_path(),
                base_path('public'),
            ]));

            $possiblePaths = [];
            foreach ($candidateFolderNames as $cfn) {
                foreach ($baseDirs as $bd) {
                    $possiblePaths[] = $bd . '/' . $cfn;
                }
            }
            $possiblePaths = array_unique($possiblePaths);

            // Prioritize directories that actually contain user router sessions
            if ($isMikhmon) {
                $hasRealMikhmonConfigs = function ($dir) {
                    $cfg = $dir . '/include/config.php';
                    $cfgBak = $dir . '/include/config.php.bak';
                    if (file_exists($cfg)) {
                        $content = @file_get_contents($cfg);
                        if ($content && preg_match('/\$data\[[\'"](?!mikhmon)[^\'"]+[\'"]\]/i', $content)) {
                            return true;
                        }
                    }
                    if (file_exists($cfgBak)) {
                        $content = @file_get_contents($cfgBak);
                        if ($content && preg_match('/\$data\[[\'"](?!mikhmon)[^\'"]+[\'"]\]/i', $content)) {
                            return true;
                        }
                    }
                    return false;
                };

                usort($possiblePaths, function ($a, $b) use ($hasRealMikhmonConfigs) {
                    $aHas = $hasRealMikhmonConfigs($a) ? 1 : 0;
                    $bHas = $hasRealMikhmonConfigs($b) ? 1 : 0;
                    return $bHas <=> $aHas;
                });
            }

            $standalonePath = null;
            foreach ($possiblePaths as $p) {
                if (is_dir($p) && (file_exists($p . '/index.php') || file_exists($p . '/login.php') || file_exists($p . '/admin.php'))) {
                    $standalonePath = $p;
                    break;
                }
            }

            // If not found on disk, but subscription exists in DB -> auto-deploy
            if (!$standalonePath && $subscription) {
                try {
                    if ($isKas) {
                        (new \App\Services\BookkeepingProvisioner)->deploy($subscription);
                    } elseif ($isMikhmon) {
                        (new \App\Services\MikhmonProvisioner)->deploy($subscription);
                    }
                } catch (\Throwable $e) {}

                // Re-check after deploy
                foreach ($possiblePaths as $p) {
                    if (is_dir($p) && (file_exists($p . '/index.php') || file_exists($p . '/login.php') || file_exists($p . '/admin.php'))) {
                        $standalonePath = $p;
                        break;
                    }
                }
                if (!$standalonePath) {
                    foreach ($possiblePaths as $p) {
                        if (is_dir($p)) {
                            $standalonePath = $p;
                            break;
                        }
                    }
                }
            }

            // If neither DB subscription nor physical folder exists on disk -> 404
            if (!$standalonePath && !$subscription) {
                return response()->view('errors.404', [], 404);
            }

            if ($standalonePath && is_dir($standalonePath)) {
                // Auto-heal user configs if needed
                if ($isMikhmon) {
                    try {
                        (new \App\Services\MikhmonProvisioner)->healAndRestoreUserConfigs($standalonePath, $subscription?->subdomain ?: $standaloneFolder);
                    } catch (\Throwable $e) {}
                }

                // Auto-sync updated code files from template if content hash differs from deployed instance
                if ($isKas) {
                    $templateCheck = resource_path('bookkeeping-template/pages/chat.php');
                    $deployCheck   = $standalonePath . '/pages/chat.php';
                    $templateTx    = resource_path('bookkeeping-template/pages/transactions.php');
                    $deployTx      = $standalonePath . '/pages/transactions.php';
                    $templateDb    = resource_path('bookkeeping-template/include/db.php');
                    $deployDb      = $standalonePath . '/include/db.php';
                    $templateLogo  = resource_path('bookkeeping-template/assets/logo.png');
                    $deployLogo    = $standalonePath . '/assets/logo.png';
                    $templateDash  = resource_path('bookkeeping-template/pages/dashboard.php');
                    $deployDash    = $standalonePath . '/pages/dashboard.php';
                    $templateRep   = resource_path('bookkeeping-template/pages/reports.php');
                    $deployRep     = $standalonePath . '/pages/reports.php';
                    $templateCat   = resource_path('bookkeeping-template/pages/categories.php');
                    $deployCat     = $standalonePath . '/pages/categories.php';
                    $needsSync = false;

                    if (file_exists($templateCheck) && (!file_exists($deployCheck) || md5_file($templateCheck) !== md5_file($deployCheck))) {
                        $needsSync = true;
                    } elseif (file_exists($templateTx) && (!file_exists($deployTx) || md5_file($templateTx) !== md5_file($deployTx))) {
                        $needsSync = true;
                    } elseif (file_exists($templateDb) && (!file_exists($deployDb) || md5_file($templateDb) !== md5_file($deployDb))) {
                        $needsSync = true;
                    } elseif (file_exists($templateDash) && (!file_exists($deployDash) || md5_file($templateDash) !== md5_file($deployDash))) {
                        $needsSync = true;
                    } elseif (file_exists($templateRep) && (!file_exists($deployRep) || md5_file($templateRep) !== md5_file($deployRep))) {
                        $needsSync = true;
                    } elseif (file_exists($templateCat) && (!file_exists($deployCat) || md5_file($templateCat) !== md5_file($deployCat))) {
                        $needsSync = true;
                    } elseif (file_exists($templateLogo) && (!file_exists($deployLogo) || md5_file($templateLogo) !== md5_file($deployLogo))) {
                        $needsSync = true;
                    }

                    if ($needsSync) {
                        try {
                            (new \App\Services\BookkeepingProvisioner)->syncCode($standalonePath);
                        } catch (\Throwable $e) {}
                    }
                } elseif ($isMikhmon) {
                    $isRos7 = ($subscription && ($subscription->ros_version ?? '6') === '7');
                    $templatePath = resource_path($isRos7 ? 'mikhmon-template-v7' : 'mikhmon-template');
                    $mikhmonCheckFiles = [
                        'index.php',
                        'admin.php',
                        'settings/settings.php',
                        'settings/sessions.php',
                        'settings/settheme.php',
                        'settings/setlang.php',
                        'settings/vouchereditor.php',
                        'hotspot/generateuser.php',
                        'lib/routeros_api.class.php',
                        'lib/formatbytesbites.php',
                        'dashboard/aload.php',
                        'dashboard/home.php',
                        'traffic/traffic.php',
                        'voucher/print.php',
                        'report/selling.php',
                        'report/livereport.php',
                        'report/resumereport.php',
                        'report/print.php',
                        'report/userlog.php',
                        'include/userlog.php',
                        'include/login.php',
                        'include/menu.php',
                        'include/bottomnav.php',
                        'include/headhtml.php',
                        'settings/uplogo.php',
                        'settings/templateselector.php',
                        'hotspot/adduserprofile.php',
                        'hotspot/userprofilebyname.php',
                        'hotspot/sync_schedulers.php',
                        'voucher/template.php',
                        'voucher/template-small.php',
                        'voucher/default.php',
                        'voucher/default-small.php',
                        'process/removereport.php',
                        'buy.php',
                        'buy_process.php',
                        'telegram_webhook.php',
                        'api_webhook.php',
                        'callback.php',
                        'include/whatsapp.php',
                        'include/whatsapp_helper.php',
                        'include/telegram.php',
                        'include/telegram_helper.php',
                        'include/noderapay.php',
                        'include/readcfg.php',
                        'index.php',
                        'img/logo.png',
                        'sw.js',
                        'manifest.json',
                        'manifest-buy.json',
                    ];

                    $needsSync = false;
                    foreach ($mikhmonCheckFiles as $tFile) {
                        $srcFile = $templatePath . '/' . $tFile;
                        $dstFile = $standalonePath . '/' . $tFile;
                        if (file_exists($srcFile) && (!file_exists($dstFile) || md5_file($srcFile) !== md5_file($dstFile))) {
                            $needsSync = true;
                            break;
                        }
                    }

                    if ($needsSync) {
                        try {
                            (new \App\Services\MikhmonProvisioner)->syncCode($standalonePath, $isRos7 ? '7' : '6');
                        } catch (\Throwable $e) {}
                    }
                }

                // Pre-synchronize PHP Superglobals for standalone apps so cookie & query lookups are accurate
                $_GET = array_merge($request->query->all(), $_GET ?? []);
                $_POST = array_merge($request->post() ?: $request->request->all(), $_POST ?? []);
                $_COOKIE = array_merge($_COOKIE ?? [], $request->cookies->all());

                $cleanUri = trim($relativeUri, '/');
                foreach ($candidateFolderNames as $cfn) {
                    if (str_starts_with($cleanUri, $cfn . '/')) {
                        $cleanUri = substr($cleanUri, strlen($cfn) + 1);
                        break;
                    }
                }

                $targetFile = null;

                // Explicit clean shortcuts
                if ($isMikhmon && ($cleanUri === 'login' || $cleanUri === 'admin') && file_exists($standalonePath . '/admin.php')) {
                    $targetFile = $standalonePath . '/admin.php';
                    $_GET['id'] = 'login';
                    $request->query->set('id', 'login');
                } elseif ($isMikhmon && $cleanUri === 'buy' && file_exists($standalonePath . '/buy.php')) {
                    $targetFile = $standalonePath . '/buy.php';
                } elseif (empty($cleanUri) || $cleanUri === 'index.php') {
                    // Check if request is targeting Mikhmon router dashboard (via session, hotspot, report, etc.)
                    $isMikhmonDashboardRequest = $isMikhmon && (
                        $request->has('session') ||
                        $request->has('hotspot') ||
                        $request->has('hotspot-user') ||
                        $request->has('user-profile') ||
                        $request->has('report') ||
                        $request->has('system') ||
                        $request->has('traffic') ||
                        $request->has('status') ||
                        $request->has('ppp') ||
                        $request->has('quick-print') ||
                        $request->has('remove-user-active') ||
                        $request->has('remove-report') ||
                        !empty($_GET['session']) ||
                        !empty($_GET['hotspot'])
                    );

                    // Check if request is targeting Mikhmon admin actions (via id=...)
                    $isMikhmonAdminAction = $isMikhmon && ($request->has('id') || !empty($_GET['id']));

                    if ($isMikhmonDashboardRequest && file_exists($standalonePath . '/index.php')) {
                        // Any request with MikroTik session or hotspot query belongs to index.php
                        $targetFile = $standalonePath . '/index.php';
                    } elseif ($isMikhmonAdminAction && file_exists($standalonePath . '/admin.php')) {
                        // Any request with id=... belongs to admin.php
                        $targetFile = $standalonePath . '/admin.php';
                    } elseif ($isMikhmon && file_exists($standalonePath . '/buy.php')) {
                        // Pure root landing page (no query params at all)
                        $isAdminLoggedIn = false;
                        if (session_status() === PHP_SESSION_NONE) {
                            @ini_set('session.cookie_path', '/');
                            @session_start();
                        }
                        if (!empty($_SESSION['mikhmon'])) {
                            $isAdminLoggedIn = true;
                        } elseif (!empty($_COOKIE['mikhmon_remember'])) {
                            $cfgFile = $standalonePath . '/include/config.php';
                            $readCfgFile = $standalonePath . '/include/readcfg.php';
                            if (file_exists($cfgFile)) {
                                @include_once $cfgFile;
                                @include_once $readCfgFile;
                                $decodedRemember = @base64_decode($_COOKIE['mikhmon_remember']);
                                if ($decodedRemember && strpos($decodedRemember, '|') !== false) {
                                    list($rUser, $rHash) = explode('|', $decodedRemember, 2);
                                    $expectedHash = hash('sha256', ($useradm ?? '') . ':' . ($passadm ?? '') . ':mikhmon_nodera_auth');
                                    if (!empty($useradm) && $rUser === $useradm && hash_equals($expectedHash, $rHash)) {
                                        $_SESSION['mikhmon'] = $useradm;
                                        $isAdminLoggedIn = true;
                                    }
                                }
                            }
                        }

                        if ($isAdminLoggedIn) {
                            $targetFile = $standalonePath . '/admin.php';
                            if (empty($_GET['id'])) {
                                $_GET['id'] = 'sessions';
                                $request->query->set('id', 'sessions');
                            }
                        } else {
                            $targetFile = $standalonePath . '/buy.php';
                        }
                    } else {
                        $targetFile = $standalonePath . '/index.php';
                    }
                } else {
                    $candidate = $standalonePath . '/' . $cleanUri;
                    if (is_dir($candidate)) {
                        if (file_exists($candidate . '/index.php')) {
                            $targetFile = $candidate . '/index.php';
                        }
                    } elseif (file_exists($candidate)) {
                        $targetFile = $candidate;
                    } elseif (file_exists($candidate . '.php')) {
                        $targetFile = $candidate . '.php';
                    }
                }

                // Fallback to template for webhooks if missing in standalone directory
                if (!$targetFile || !file_exists($targetFile)) {
                    if ($cleanUri === 'telegram_webhook.php' || str_ends_with($cleanUri, '/telegram_webhook.php')) {
                        $tplWebhook = resource_path('mikhmon-template-v7/telegram_webhook.php');
                        if (!file_exists($tplWebhook)) {
                            $tplWebhook = resource_path('mikhmon-template/telegram_webhook.php');
                        }
                        if (file_exists($tplWebhook)) {
                            @copy($tplWebhook, $standalonePath . '/telegram_webhook.php');
                            $targetFile = file_exists($standalonePath . '/telegram_webhook.php') ? ($standalonePath . '/telegram_webhook.php') : $tplWebhook;
                        }
                    } elseif ($cleanUri === 'api_webhook.php' || str_ends_with($cleanUri, '/api_webhook.php')) {
                        $tplApi = resource_path('mikhmon-template-v7/api_webhook.php');
                        if (!file_exists($tplApi)) {
                            $tplApi = resource_path('mikhmon-template/api_webhook.php');
                        }
                        if (file_exists($tplApi)) {
                            @copy($tplApi, $standalonePath . '/api_webhook.php');
                            $targetFile = file_exists($standalonePath . '/api_webhook.php') ? ($standalonePath . '/api_webhook.php') : $tplApi;
                        }
                    }
                }

                // Fallback to index.php or login.php or admin.php if not matched
                if (!$targetFile || !file_exists($targetFile)) {
                    if (file_exists($standalonePath . '/index.php')) {
                        $targetFile = $standalonePath . '/index.php';
                    } elseif (file_exists($standalonePath . '/login.php')) {
                        $targetFile = $standalonePath . '/login.php';
                    } elseif (file_exists($standalonePath . '/admin.php')) {
                        $targetFile = $standalonePath . '/admin.php';
                    }
                }

                if ($targetFile && file_exists($targetFile)) {
                    $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                    if ($ext === 'php') {
                        // Preload standalone dependencies (e.g. RouterosAPI) to eliminate missing class errors
                        if ($isMikhmon) {
                            if (!class_exists('RouterosAPI')) {
                                $apiFiles = [
                                    $standalonePath . '/lib/routeros_api.class.php',
                                    dirname($targetFile) . '/../lib/routeros_api.class.php',
                                    resource_path('mikhmon-template-v7/lib/routeros_api.class.php'),
                                    resource_path('mikhmon-template/lib/routeros_api.class.php'),
                                ];
                                foreach ($apiFiles as $af) {
                                    if (file_exists($af)) {
                                        require_once $af;
                                        break;
                                    }
                                }
                            }
                            if (!function_exists('formatBytes')) {
                                $fmtFiles = [
                                    $standalonePath . '/lib/formatbytesbites.php',
                                    dirname($targetFile) . '/../lib/formatbytesbites.php',
                                    resource_path('mikhmon-template-v7/lib/formatbytesbites.php'),
                                    resource_path('mikhmon-template/lib/formatbytesbites.php'),
                                ];
                                foreach ($fmtFiles as $ff) {
                                    if (file_exists($ff)) {
                                        require_once $ff;
                                        break;
                                    }
                                }
                            }
                        }

                        chdir(dirname($targetFile));
                        $_SERVER['SCRIPT_FILENAME'] = $targetFile;
                        $_SERVER['SCRIPT_NAME'] = '/' . (empty($cleanUri) ? basename($targetFile) : $cleanUri);
                        $_SERVER['PHP_SELF'] = '/' . (empty($cleanUri) ? basename($targetFile) : $cleanUri);
                        $_SERVER['HTTP_HOST'] = $host;
                        $_SERVER['REQUEST_METHOD'] = $request->method();
                        $_SERVER['REQUEST_URI'] = $request->getRequestUri();
                        $_SERVER['QUERY_STRING'] = $request->getQueryString() ?? '';

                        // Synchronize PHP Superglobals for standalone apps
                        $_GET = array_merge($request->query->all(), $_GET ?? []);
                        $_POST = array_merge($request->post() ?: $request->request->all(), $_POST ?? []);
                        $_COOKIE = array_merge($_COOKIE ?? [], $request->cookies->all());
                        $GLOBALS['LARAVEL_RAW_INPUT'] = $request->getContent();
                        $GLOBALS['HTTP_RAW_POST_DATA'] = $request->getContent();
                        
                        $convertUploadedFile = function ($file) use (&$convertUploadedFile) {
                            if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                                return [
                                    'name'     => $file->getClientOriginalName(),
                                    'type'     => $file->getClientMimeType() ?: ($file->getMimeType() ?? 'application/octet-stream'),
                                    'tmp_name' => $file->getPathname(),
                                    'error'    => $file->getError(),
                                    'size'     => $file->getSize(),
                                ];
                            }
                            if (is_array($file)) {
                                $res = [];
                                foreach ($file as $k => $v) {
                                    $res[$k] = $convertUploadedFile($v);
                                }
                                return $res;
                            }
                            return $file;
                        };

                        $syncedFiles = [];
                        foreach ($request->files->all() as $key => $fileItem) {
                            $syncedFiles[$key] = $convertUploadedFile($fileItem);
                        }
                        $_FILES = !empty($syncedFiles) ? $syncedFiles : ($_FILES ?? []);
                        $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);

                        if (!isset($_GET['id']) && $request->has('id')) {
                            $_GET['id'] = $request->query('id');
                        }
                        if (!isset($_GET['session']) && $request->has('session')) {
                            $_GET['session'] = $request->query('session');
                        }

                        // Proteksi Auto-Heal Mutlak: Periksa semua file konfigurasi PHP sebelum eksekusi
                        $targetDir = dirname($targetFile);
                        $prov = app(\App\Services\MikhmonProvisioner::class);
                        $subname = basename($targetDir);

                        $cfgFiles = glob($targetDir . '/include/*.php') ?: [];
                        $cfgFiles[] = $targetDir . '/config/license.php';
                        foreach ($cfgFiles as $cFile) {
                            if (file_exists($cFile)) {
                                $cCode = @file_get_contents($cFile);
                                if ($cCode !== false && !$prov->isPhpSyntaxValid($cCode)) {
                                    $prov->healAndRestoreUserConfigs($targetDir, $subname);
                                    break;
                                }
                            }
                        }

                        try {
                            require $targetFile;
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error("[TenantDetection] Standalone PHP error on {$targetFile}: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                            
                            // Auto-heal configs and attempt safe retry
                            try {
                                $prov->healAndRestoreUserConfigs($targetDir, $subname);
                                require $targetFile;
                            } catch (\Throwable $retryErr) {
                                \Illuminate\Support\Facades\Log::critical("[TenantDetection] Standalone PHP retry failed on {$targetFile}: " . $retryErr->getMessage());
                                if (!headers_sent()) {
                                    http_response_code(503);
                                }
                                echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Layanan Dalam Sinkronisasi</title><style>body{font-family:sans-serif;background:#f8fafc;color:#1e293b;padding:40px 20px;text-align:center;}.box{max-width:500px;margin:40px auto;background:#fff;border-radius:12px;padding:30px;box-shadow:0 4px 20px rgba(0,0,0,0.08);}.btn{display:inline-block;padding:10px 20px;background:#0062ff;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;margin-top:15px;}</style></head><body><div class="box"><h2>🔄 Sinkronisasi Layanan</h2><p>Layanan sedang melakukan pemulihan dan pembaruan sistem. Silakan muat ulang halaman beberapa saat lagi.</p><a href="javascript:location.reload()" class="btn">Muat Ulang Halaman</a></div></body></html>';
                            }
                        }
                        exit;
                    }

                    $mimeTypes = [
                        'css'   => 'text/css',
                        'js'    => 'application/javascript',
                        'json'  => 'application/manifest+json',
                        'webmanifest' => 'application/manifest+json',
                        'png'   => 'image/png',
                        'jpg'   => 'image/jpeg',
                        'jpeg'  => 'image/jpeg',
                        'gif'   => 'image/gif',
                        'svg'   => 'image/svg+xml',
                        'ico'   => 'image/x-icon',
                        'woff'  => 'font/woff',
                        'woff2' => 'font/woff2',
                        'ttf'   => 'font/ttf',
                        'eot'   => 'application/vnd.ms-fontobject',
                    ];
                    $mime = $mimeTypes[$ext] ?? (@mime_content_type($targetFile) ?: 'application/octet-stream');
                    return response()->file($targetFile, ['Content-Type' => $mime]);
                }
            }

            return response()->view('errors.404', [], 404);
        }

        // Standalone mode, Localhost, IP addresses, dan Apex Domain tidak memiliki subdomain tenant
        if (
            filter_var(env('STANDALONE_MODE', false), FILTER_VALIDATE_BOOLEAN) ||
            config('app.standalone_mode', false) ||
            in_array($host, ['localhost', '127.0.0.1', '::1']) ||
            filter_var($host, FILTER_VALIDATE_IP) ||
            $host === $baseDomain ||
            $host === 'www.' . $baseDomain ||
            !str_ends_with($host, '.' . $baseDomain)
        ) {
            $request->attributes->set('tenant_id', null);
            if (!session('impersonating') && ($host === $baseDomain || $host === 'www.' . $baseDomain)) {
                session()->forget(['tenant_id', 'tenant_slug', 'tenant_name']);
            }
            return $next($request);
        }

        if (!$subdomain || $subdomain === 'www') {
            $request->attributes->set('tenant_id', null);
            if (!session('impersonating')) {
                session()->forget(['tenant_id', 'tenant_slug', 'tenant_name']);
            }
            return $next($request);
        }

        // ── Panel subdomain → VPN store (tanpa tenant context) ──
        if ($subdomain === 'panel') {
            $request->attributes->set('tenant_id', null);
            session()->forget(['tenant_id', 'tenant_slug', 'tenant_name']);
            return $next($request);
        }

        // ── Shop subdomain → E-Commerce store (tanpa tenant context) ──
        if ($subdomain === 'shop') {
            $request->attributes->set('tenant_id', null);
            session()->forget(['tenant_id', 'tenant_slug', 'tenant_name']);
            return $next($request);
        }

        // ── Gateway subdomain → NODERA PAY Gateway (tanpa tenant context) ──
        if ($subdomain === 'gateway') {
            $request->attributes->set('tenant_id', null);
            session()->forget(['tenant_id', 'tenant_slug', 'tenant_name']);
            return $next($request);
        }

        // ── WA Gateway subdomain → NODERA WA Gateway (tanpa tenant context) ──
        if ($subdomain === 'wa' || $subdomain === 'wagateway') {
            $request->attributes->set('tenant_id', null);
            session()->forget(['tenant_id', 'tenant_slug', 'tenant_name']);
            return $next($request);
        }

        // ── Tenant subdomain (cached metadata for ultra-fast response & safe unserialize) ──
        $cacheKey = "tenant_meta:slug:{$subdomain}";
        $tenantData = null;
        try {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            // Handle legacy incomplete class or corrupted cache
            if ($cached && !($cached instanceof \__PHP_Incomplete_Class) && is_array($cached)) {
                $tenantData = $cached;
            }
        } catch (\Throwable $e) {
            $tenantData = null;
        }

        // Also purge old legacy serialized model cache key if it exists
        try {
            \Illuminate\Support\Facades\Cache::forget("tenant:slug:{$subdomain}");
        } catch (\Throwable $e) {}

        if (!$tenantData) {
            $t = Tenant::withoutGlobalScopes()->where('slug', $subdomain)->first();
            if ($t) {
                $tenantData = [
                    'id'         => (int) $t->id,
                    'name'       => (string) $t->name,
                    'slug'       => (string) $t->slug,
                    'is_active'  => (bool) $t->is_active,
                    'expired_at' => $t->expired_at ? (string) $t->expired_at : null,
                ];
                \Illuminate\Support\Facades\Cache::put($cacheKey, $tenantData, 120);
            }
        }

        // Dynamic auto-provisioning untuk demo.dgtlnetsolution.com jika belum terdaftar/terbuat di database
        if ((!$tenantData || empty($tenantData['is_active'])) && $subdomain === 'demo') {
            try {
                $seeder = new \Database\Seeders\DemoTenantSeeder();
                $seeder->run();
                $t = Tenant::withoutGlobalScopes()->where('slug', 'demo')->first();
                if ($t) {
                    $tenantData = [
                        'id'         => (int) $t->id,
                        'name'       => (string) $t->name,
                        'slug'       => (string) $t->slug,
                        'is_active'  => (bool) $t->is_active,
                        'expired_at' => $t->expired_at ? (string) $t->expired_at : null,
                    ];
                }
            } catch (\Throwable $e) {
                // Fail-safe
            }
        }

        if ($tenantData) {
            $isExpired = false;
            if (!empty($tenantData['expired_at'])) {
                try {
                    $isExpired = \Carbon\Carbon::parse($tenantData['expired_at'])->isPast();
                } catch (\Throwable $e) {}
            }

            if ($isExpired || empty($tenantData['is_active'])) {
                $scheme = $request->getScheme();
                $port = $request->getPort();
                $portSuffix = ($port && !in_array($port, [80, 443])) ? ":{$port}" : '';
                $panelUrl = $scheme . '://panel.' . $baseDomain . $portSuffix;

                return response()->view('errors.tenant-expired', [
                    'subdomain'   => $subdomain,
                    'tenant'      => (object) $tenantData,
                    'expired_at'  => $tenantData['expired_at'] ?? null,
                    'is_active'   => (bool) ($tenantData['is_active'] ?? false),
                    'panelUrl'    => $panelUrl,
                ], 403);
            }

            $request->attributes->set('tenant_id', (int) $tenantData['id']);
            $request->attributes->set('tenant_slug', $tenantData['slug']);
            $request->attributes->set('tenant_name', $tenantData['name']);
            session([
                'tenant_id'   => (int) $tenantData['id'],
                'tenant_slug' => $tenantData['slug'],
                'tenant_name' => $tenantData['name'],
            ]);
            view()->share('currentTenant', (object) $tenantData);
        } else {
            return response()->view('errors.tenant-not-found', [
                'subdomain' => $subdomain,
            ], 404);
        }

        return $next($request);
    }

    /**
     * Render modern expired / inactive page for standalone SaaS apps (Mikhmon & Bookkeeping).
     */
    protected function renderExpiredOrInactivePage(string $serviceTitle, string $subname, ?string $expiryDate, string $status, string $renewUrl)
    {
        $isInactive = in_array($status, ['SUSPENDED', 'DISABLED', 'BLOCKED']);
        $title = $isInactive ? 'Layanan Dinonaktifkan' : 'Masa Aktif Berakhir';
        $badgeText = $isInactive ? 'Status: Dinonaktifkan' : 'Status: Masa Aktif Habis';
        $colorClass = $isInactive ? 'danger' : 'warning';
        $icon = $isInactive 
            ? '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'
            : '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>';
        $desc = $isInactive
            ? "Aplikasi <strong>" . htmlspecialchars($serviceTitle . " (" . $subname . ")") . "</strong> sedang dinonaktifkan oleh administrator."
            : "Masa aktif berlangganan <strong>" . htmlspecialchars($serviceTitle . " (" . $subname . ")") . "</strong> telah berakhir" . ($expiryDate ? " pada <strong>" . htmlspecialchars(date('d M Y', strtotime($expiryDate))) . "</strong>" : "") . ". Silakan lakukan perpanjangan langganan untuk dapat mengakses kembali.";
        
        $html = '<!DOCTYPE html><html lang="id" data-theme="dark"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>' . $title . ' — NODERA</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"><style>:root{--bg:#090d16;--card-bg:rgba(18,24,40,0.78);--card-border:rgba(255,255,255,0.08);--text-main:#f1f5f9;--text-muted:#94a3b8;--primary:#38bdf8;--primary-hover:#0ea5e9;--danger:#f87171;--danger-bg:rgba(248,113,113,0.12);--warning:#fbbf24;--warning-bg:rgba(251,191,36,0.12);--pill-bg:rgba(255,255,255,0.04);--pill-border:rgba(255,255,255,0.07);}[data-theme="light"]{--bg:#f4f6fb;--card-bg:rgba(255,255,255,0.92);--card-border:rgba(0,0,0,0.08);--text-main:#0f172a;--text-muted:#64748b;--primary:#0284c7;--primary-hover:#0369a1;--danger:#dc2626;--danger-bg:rgba(220,38,38,0.08);--warning:#d97706;--warning-bg:rgba(217,119,6,0.08);--pill-bg:rgba(0,0,0,0.03);--pill-border:rgba(0,0,0,0.06);}*{box-sizing:border-box;margin:0;padding:0;}body{font-family:\'Plus Jakarta Sans\',\'Poppins\',sans-serif;background-color:var(--bg);color:var(--text-main);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;position:relative;overflow-x:hidden;transition:background-color .3s,color .3s;}.ambient-glow{position:fixed;width:480px;height:480px;border-radius:50%;filter:blur(120px);pointer-events:none;opacity:.22;z-index:0;}.glow-1{top:-80px;left:-80px;background:' . ($isInactive ? '#f87171' : '#fbbf24') . ';}.glow-2{bottom:-80px;right:-80px;background:#38bdf8;}.card-container{position:relative;z-index:10;width:100%;max-width:440px;background:var(--card-bg);border:1px solid var(--card-border);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border-radius:28px;padding:36px 30px;text-align:center;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);transition:all .3s;}.theme-btn{position:absolute;top:20px;right:20px;width:36px;height:36px;border-radius:50%;border:1px solid var(--card-border);background:var(--pill-bg);color:var(--text-muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;transition:all .2s;}.theme-btn:hover{color:var(--text-main);transform:scale(1.06);}.icon-badge{width:72px;height:72px;margin:0 auto 18px;border-radius:24px;display:flex;align-items:center;justify-content:center;font-size:32px;background:var(--' . $colorClass . '-bg);color:var(--' . $colorClass . ');box-shadow:inset 0 0 0 1px var(--card-border);}.status-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 14px;border-radius:9999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:14px;background:var(--' . $colorClass . '-bg);color:var(--' . $colorClass . ');}.title{font-size:20px;font-weight:800;color:var(--text-main);margin-bottom:8px;letter-spacing:-.02em;}.desc{font-size:13.5px;color:var(--text-muted);line-height:1.6;margin-bottom:22px;}.info-pill{background:var(--pill-bg);border:1px solid var(--pill-border);border-radius:16px;padding:14px 18px;margin-bottom:24px;text-align:left;font-size:12.5px;}.info-row{display:flex;justify-content:space-between;align-items:center;padding:3px 0;}.info-label{color:var(--text-muted);font-size:12px;}.info-val{color:var(--text-main);font-weight:600;font-family:monospace;}.btn-primary{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:13px 20px;border-radius:14px;background:var(--primary);color:#090d16;font-size:13.5px;font-weight:700;text-decoration:none;transition:all .2s;box-shadow:0 4px 14px rgba(56,189,248,.25);}.btn-primary:hover{background:var(--primary-hover);transform:translateY(-1px);}.btn-primary:active{transform:scale(.98);}.footer-brand{margin-top:24px;font-size:11px;font-weight:600;color:var(--text-muted);opacity:.75;letter-spacing:.04em;}</style></head><body><div class="ambient-glow glow-1"></div><div class="ambient-glow glow-2"></div><div class="card-container"><button class="theme-btn" onclick="toggleTheme()" id="themeBtn" title="Ganti Mode Tema"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg></button><div class="icon-badge">' . $icon . '</div><div class="status-badge">' . $badgeText . '</div><h1 class="title">' . $title . '</h1><p class="desc">' . $desc . '</p><div class="info-pill"><div class="info-row"><span class="info-label">Layanan:</span><span class="info-val" style="font-family:inherit;">' . htmlspecialchars($serviceTitle) . '</span></div><div class="info-row"><span class="info-label">Subdomain:</span><span class="info-val">' . htmlspecialchars($subname) . '</span></div>' . (!empty($expiryDate) ? '<div class="info-row"><span class="info-label">Kedaluwarsa:</span><span class="info-val" style="color:var(--' . $colorClass . ');">' . htmlspecialchars($expiryDate) . '</span></div>' : '') . '</div><a href="' . htmlspecialchars($renewUrl) . '" class="btn-primary" target="_blank">' . ($isInactive ? 'Hubungi Administrator' : 'Perpanjang Langganan Sekarang') . '</a><div class="footer-brand">NODERA · Digital Network Solution</div></div><script>(function(){var theme=localStorage.getItem(\'n_theme\')||(window.matchMedia(\'(prefers-color-scheme: dark)\').matches?\'dark\':\'light\');document.documentElement.setAttribute(\'data-theme\',theme);})();function toggleTheme(){var curr=document.documentElement.getAttribute(\'data-theme\');var next=curr===\'dark\'?\'light\':\'dark\';document.documentElement.setAttribute(\'data-theme\',next);localStorage.setItem(\'n_theme\',next);}</script></body></html>';
        return response($html, 403)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
