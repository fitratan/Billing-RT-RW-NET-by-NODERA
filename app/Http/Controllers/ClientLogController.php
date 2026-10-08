<?php

namespace App\Http\Controllers;

use App\Models\ClientLog;
use App\Models\Customer;
use App\Models\Collector;
use App\Models\Setting;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ClientLogController extends Controller
{
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'level' => ['sometimes', 'string', 'in:info,warning,error,debug'],
            'message' => ['required', 'string', 'max:1000'],
            'stack' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
        ])->validate();

        $user = null;
        $userRole = null;

        // 1. Web authenticated user (Admin, Superadmin, Staff, etc.)
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            $userRole = $user?->role ?? 'user';
        }
        // 2. VPN User (Panel)
        elseif (config()->has('auth.guards.vpn') && Auth::guard('vpn')->check()) {
            $user = Auth::guard('vpn')->user();
            $userRole = 'vpn_user';
        }
        // 3. Customer Portal Session
        elseif (session('customer_id')) {
            try {
                $customer = Customer::withoutGlobalScopes()->find(session('customer_id'));
                if ($customer) {
                    $user = $customer;
                    $userRole = 'customer';
                }
            } catch (Throwable $e) {
            }
        }
        // 4. Technician Session
        elseif (session('technician_id')) {
            try {
                $tech = User::find(session('technician_id')) ?? Customer::find(session('technician_id'));
                if ($tech) {
                    $user = $tech;
                    $userRole = 'technician';
                }
            } catch (Throwable $e) {
            }
        }
        // 5. Collector Session
        elseif (session('collector_id')) {
            try {
                $coll = Collector::find(session('collector_id')) ?? User::find(session('collector_id'));
                if ($coll) {
                    $user = $coll;
                    $userRole = 'collector';
                }
            } catch (Throwable $e) {
            }
        }
        // 6. Generic request user fallback
        elseif ($request->user()) {
            $user = $request->user();
            $userRole = $user?->role ?? 'user';
        }

        $tenantId = null;
        if ($user) {
            if (method_exists($user, 'tenant') && $user->tenant) {
                $tenantId = $user->tenant->id;
            } elseif (isset($user->tenant_id)) {
                $tenantId = $user->tenant_id;
            }
        }
        if (!$tenantId && session('tenant_id')) {
            $tenantId = session('tenant_id');
        }

        $level = $validated['level'] ?? 'error';
        $pageUrl = substr($request->input('url', '') ?: $request->fullUrl(), 0, 500);

        $cleanMessage = $this->sanitizeSensitiveData($validated['message'] ?? '');
        $cleanStack = $this->sanitizeSensitiveData($validated['stack'] ?? null);
        $cleanSource = $this->sanitizeSensitiveData($validated['source'] ?? ($user ? 'user:'.$user->id : 'guest'));

        try {
            $log = ClientLog::create([
                'tenant_id' => $tenantId,
                'level' => $level,
                'message' => $cleanMessage,
                'stack' => $cleanStack,
                'source' => $cleanSource,
                'role' => $userRole,
                'route' => $request->header('X-Route') ?? $request->input('route'),
                'url' => $pageUrl,
                'user_agent' => mb_substr($request->userAgent() ?? 'unknown', 0, 255),
                'ip' => $request->ip(),
            ]);
            $logId = $log->id;
        } catch (Throwable $e) {
            Log::error('Failed creating ClientLog: ' . $e->getMessage());
            $logId = null;
        }

        // Forward frontend/client errors to Telegram Superadmin & Forum Error Topic (skip bot/crawler noise & transient chunk errors)
        $userAgent = $request->userAgent() ?? '';
        $isBot = preg_match('/(Googlebot|bingbot|Baiduspider|YandexBot|AhrefsBot|PetalBot|SemrushBot|DotBot|MJ12bot|crawler|spider|headless|bot)/i', $userAgent);
        $message = $cleanMessage;
        $fullLogContext = $message . ' ' . ($cleanStack ?? '') . ' ' . ($cleanSource ?? '');
        $isTransientChunkError = preg_match('/(chunk|dynamically imported|Network error|Failed to fetch|Load failed|stale_chunk|ServiceWorker|service worker|Failed to update a ServiceWorker|fetching the script)/i', $fullLogContext);
        $isThirdPartyInjectedScript = preg_match('/(chrome-extension:\/\/|moz-extension:\/\/|safari-extension:\/\/|iabjs:|navigation_performance_logger|postMessage: Java exception|challenges\.cloudflare\.com|turnstile|^pa$|^Error:\s*pa$|maximum call stack size exceeded|evaluating\s*[\'"]?a\.I[\'"]?|executors\/200\.js)/i', $fullLogContext);
        $isFlashModalNotice = str_starts_with($message, '[System Error Modal]') || str_starts_with($cleanSource ?? '', 'Flash Error') || preg_match('/(Circuit Breaker|sedang dalam cooldown|tidak dapat terhubung|kapasitas pelanggan|password salah)/i', $message);

        if ($level === 'error' && !$isBot && !$isTransientChunkError && !$isThirdPartyInjectedScript && !$isFlashModalNotice) {
            try {
                $telegram = app(TelegramService::class);
                if ($telegram->isConfigured()) {
                    $roleLabel = $userRole ?? 'guest';
                    $userInfo = $user ? "{$user->name} (Role: {$roleLabel}, ID: {$user->id})" : 'Guest / Pengunjung';
                    $sourceInfo = $cleanSource ?? 'Browser Runtime / React';
                    $shortStack = !empty($cleanStack) ? mb_substr($cleanStack, 0, 500) : '-';
                    $appName = htmlspecialchars(config('app.name', 'NODERA'));
                    $hostDomain = htmlspecialchars(request()->getHost());

                    $tgMessage = "<b>⚠️ CLIENT ERROR ALERT — {$appName} ({$hostDomain})</b>\n\n"
                        . "┌ Detail Error\n"
                        . "├ Error: <code>" . htmlspecialchars($cleanMessage) . "</code>\n"
                        . "├ URL: <code>" . htmlspecialchars($pageUrl) . "</code>\n"
                        . "├ User: " . htmlspecialchars($userInfo) . "\n"
                        . "├ Source: <code>" . htmlspecialchars($sourceInfo) . "</code>\n"
                        . "├ Client IP: <code>" . htmlspecialchars($request->ip() ?? '-') . "</code>\n"
                        . "├ Browser: <code>" . htmlspecialchars(mb_substr($request->userAgent() ?? '-', 0, 120)) . "</code>\n"
                        . "└ Waktu: " . date('Y-m-d H:i:s T') . "\n\n"
                        . "┌ Stack Trace\n"
                        . "└ <pre>" . htmlspecialchars($shortStack) . "</pre>";

                    $telegram->sendAdminNotification($tgMessage, 'error', 'HTML');
                }
            } catch (Throwable $e) {
                Log::warning('Failed sending client error to Telegram: ' . $e->getMessage());
            }
        }

        return response()->json(['ok' => true, 'id' => $logId]);
    }

    /**
     * Sanitize sensitive data (passwords, tokens, keys) from error messages & stack traces.
     */
    private function sanitizeSensitiveData(?string $text): ?string
    {
        if (empty($text)) {
            return $text;
        }

        $patterns = [
            '/(["\']?(?:password|passwd|secret|token|api[_-]?key|auth|bearer|pppoe_password|card_number|cvv|pin)["\']?\s*[:=]\s*["\']?)([^"\'\s&,;]{3,})(["\']?)/i' => '$1***REDACTED***$3',
            '/(Bearer\s+)[A-Za-z0-9\-\._~\+\/]+=*/i' => '$1***REDACTED***',
            '/((?:client_secret|access_token|refresh_token)=[A-Za-z0-9\-\._~]+)/i' => 'token=***REDACTED***',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $text);
    }
}
