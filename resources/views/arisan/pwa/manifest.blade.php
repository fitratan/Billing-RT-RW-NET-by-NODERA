{
    "name": "{{ $subscription->business_name }}",
    "short_name": "{{ \Illuminate\Support\Str::limit($subscription->business_name, 12, '') ?: $subscription->business_name }}",
    "description": "Aplikasi Pembukuan Arisan Digital - {{ $subscription->business_name }}",
    "start_url": "/arisan-app/{{ $subdomain }}/member/login",
    "scope": "/arisan-app/{{ $subdomain }}/",
    "display": "standalone",
    "orientation": "portrait",
    "background_color": "#0f172a",
    "theme_color": "#059669",
    "icons": [
        {
            "src": "/favicon.png",
            "sizes": "192x192",
            "type": "image/png",
            "purpose": "any maskable"
        },
        {
            "src": "/favicon.png",
            "sizes": "512x512",
            "type": "image/png",
            "purpose": "any maskable"
        }
    ],
    "categories": ["finance", "productivity", "utilities"]
}
