import React, { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { ShieldCheck } from "lucide-react"

interface TurnstileProps {
    siteKey?: string;
    onSuccess: (token: string) => void;
    onError?: () => void;
    onExpire?: () => void;
    theme?: 'auto' | 'light' | 'dark';
    size?: 'normal' | 'compact' | 'flexible';
    language?: string;
    className?: string;
}

declare global {
    interface Window {
        turnstile?: {
            render: (
                container: HTMLElement | string,
                options: {
                    sitekey: string;
                    callback?: (token: string) => void;
                    'error-callback'?: () => void;
                    'expired-callback'?: () => void;
                    theme?: 'auto' | 'light' | 'dark';
                    size?: 'normal' | 'compact' | 'flexible';
                    language?: string;
                    appearance?: 'always' | 'execute' | 'interaction-only';
                }
            ) => string;
            reset: (widgetId: string) => void;
            remove: (widgetId: string) => void;
        };
        onloadTurnstileCallback?: () => void;
    }
}

export const Turnstile: React.FC<TurnstileProps> = ({
    siteKey: propSiteKey,
    onSuccess,
    onError,
    onExpire,
    theme = 'dark',
    size = 'normal',
    language = 'id',
    className = '',
}) => {
    const { props } = usePage<{
        turnstile?: { enabled?: boolean; site_key?: string };
    }>();

    const containerRef = useRef<HTMLDivElement>(null);
    const widgetIdRef = useRef<string | null>(null);
    const [isScriptLoaded, setIsScriptLoaded] = useState(false);
    const [isRendered, setIsRendered] = useState(false);

    // Keep callback refs stable to prevent effect re-triggering & re-render loops
    const onSuccessRef = useRef(onSuccess);
    const onErrorRef = useRef(onError);
    const onExpireRef = useRef(onExpire);

    useEffect(() => {
        onSuccessRef.current = onSuccess;
        onErrorRef.current = onError;
        onExpireRef.current = onExpire;
    });

    const isEnabled = props.turnstile?.enabled ?? true;
    const effectiveSiteKey = propSiteKey || props.turnstile?.site_key || '0x4AAAAAAE7NCfJO5Iu6AKOa';

    useEffect(() => {
        if (!isEnabled) {
            onSuccessRef.current('turnstile_disabled');
            return;
        }

        // Check if window.turnstile is already available
        if (typeof window !== 'undefined' && window.turnstile) {
            setIsScriptLoaded(true);
            return;
        }

        const scriptId = 'cloudflare-turnstile-script';
        let script = document.getElementById(scriptId) as HTMLScriptElement | null;
        if (!script) {
            script = document.querySelector('script[src*="challenges.cloudflare.com/turnstile"]') as HTMLScriptElement | null;
        }

        let fallbackTimer: ReturnType<typeof setTimeout> | null = null;
        let pollInterval: ReturnType<typeof setInterval> | null = null;

        if (!script) {
            script = document.createElement('script');
            script.id = scriptId;
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
            script.async = true;
            script.defer = true;
            script.onload = () => {
                setIsScriptLoaded(true);
            };
            script.onerror = () => {
                // Adblock / network block fallback
                setIsRendered(true);
                onSuccessRef.current('turnstile_fallback_token');
            };
            document.head.appendChild(script);
        } else {
            pollInterval = setInterval(() => {
                if (window.turnstile) {
                    setIsScriptLoaded(true);
                    if (pollInterval) clearInterval(pollInterval);
                }
            }, 60);
        }

        // Fallback safety timeout if script fails or is blocked by privacy tools
        fallbackTimer = setTimeout(() => {
            if (!window.turnstile) {
                setIsRendered(true);
                onSuccessRef.current('turnstile_fallback_token');
            }
        }, 5000);

        return () => {
            if (pollInterval) clearInterval(pollInterval);
            if (fallbackTimer) clearTimeout(fallbackTimer);
        };
    }, [isEnabled]);

    useEffect(() => {
        if (!isEnabled || !isScriptLoaded || !containerRef.current || !window.turnstile) {
            return;
        }

        // Clean up previous widget instance if any
        if (widgetIdRef.current) {
            try {
                window.turnstile.remove(widgetIdRef.current);
            } catch (e) {
                // Ignore removal error
            }
            widgetIdRef.current = null;
        }

        // Clear container children to prevent duplicate iframes
        if (containerRef.current) {
            containerRef.current.innerHTML = '';
        }

        try {
            const widgetId = window.turnstile.render(containerRef.current, {
                sitekey: effectiveSiteKey,
                theme: theme,
                size: size,
                language: language,
                appearance: 'always',
                callback: (token: string) => {
                    setIsRendered(true);
                    onSuccessRef.current(token);
                },
                'error-callback': () => {
                    setIsRendered(true);
                    onErrorRef.current?.();
                    // If error occurs (e.g. domain mismatch on staging), allow fallback token
                    onSuccessRef.current('turnstile_fallback_token');
                },
                'expired-callback': () => {
                    onExpireRef.current?.();
                },
            });
            widgetIdRef.current = widgetId;
            setIsRendered(true);
        } catch (e) {
            console.warn('Turnstile render warning:', e);
            setIsRendered(true);
            onSuccessRef.current('turnstile_fallback_token');
        }

        return () => {
            if (widgetIdRef.current && window.turnstile) {
                try {
                    window.turnstile.remove(widgetIdRef.current);
                } catch (e) {
                    // Ignore removal error
                }
                widgetIdRef.current = null;
            }
        };
    }, [isEnabled, isScriptLoaded, effectiveSiteKey, theme, size, language]);

    if (!isEnabled) {
        return null;
    }

    return (
        <div className={`w-full flex flex-col items-center justify-center my-2.5 ${className}`}>
            <div className="flex items-center justify-center gap-1.5 mb-1.5 text-[11px] font-medium text-slate-400 select-none">
                <ShieldCheck className="h-3.5 w-3.5 text-[#00C2FF]" />
                <span>Verifikasi Keamanan Cloudflare</span>
            </div>
            
            <div className="min-h-[65px] min-w-[300px] flex items-center justify-center relative">
                {!isRendered && (
                    <div className="absolute inset-0 flex items-center justify-center text-[11px] text-slate-500 animate-pulse">
                        Memuat verifikasi keamanan...
                    </div>
                )}
                <div ref={containerRef} className="flex justify-center items-center" />
            </div>
        </div>
    );
};

export default Turnstile;

