<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DetectDevice
{
    /** @var array<string> */
    protected array $mobilePatterns = [
        'Mobi', 'Android', 'iPhone', 'iPad', 'iPod',
        'webOS', 'BlackBerry', 'Opera Mini', 'IEMobile',
        'Windows Phone', 'Mobile Safari', 'Mobile',
    ];

    /** @var array<string> */
    protected array $tabletPatterns = [
        'iPad', 'Tablet', 'PlayBook', 'Silk',
        'Android(?!.*Mobile)', 'KFTT', 'KFJWI',
        'KFJWA', 'KFOTE', 'KFTHWI', 'KFTHWA',
        'KFAPWI', 'KFAPWA', 'KFARWI', 'KFASWI',
        'KFSAWI', 'KFSAWA',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $ua = $request->userAgent() ?? '';

        $isTablet = $this->matchAny($ua, $this->tabletPatterns);
        $isMobile = ! $isTablet && $this->matchAny($ua, $this->mobilePatterns);

        $request->merge([
            '_device_is_mobile' => $isMobile,
            '_device_is_tablet' => $isTablet,
            '_device_is_desktop' => ! $isMobile && ! $isTablet,
        ]);

        view()->share('isMobile', $isMobile || $isTablet);
        view()->share('isTablet', $isTablet);
        view()->share('isDesktop', ! $isMobile && ! $isTablet);
        view()->share('deviceType', $isTablet ? 'tablet' : ($isMobile ? 'mobile' : 'desktop'));

        return $next($request);
    }

    protected function matchAny(string $ua, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $ua)) {
                return true;
            }
        }
        return false;
    }
}
