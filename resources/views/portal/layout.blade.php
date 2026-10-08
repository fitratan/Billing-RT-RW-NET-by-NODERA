<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title', 'NODERA - Portal')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #F5F6FA; }
        .card { background: #fff; border-radius: 20px; padding: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); }
        .card-sm { padding: 16px; border-radius: 16px; }
        .stat-icon { width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .bottom-nav { backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); }
        .nav-item.active { background: linear-gradient(135deg, #2563EB, #1D4ED8); color: white; border-radius: 50px; padding: 10px 20px; box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
        .nav-item.active i { color: white !important; }
        input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.15); }
        .btn-primary { background: linear-gradient(135deg, #2563EB, #1D4ED8); transition: all 0.2s; }
        .btn-primary:active { transform: scale(0.97); }
    </style>
    @stack('styles')
</head>
<body class="antialiased">
    @yield('content')
    @stack('scripts')
</body>
</html>
