<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'NODERA')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', Courier, monospace; font-size: 12px; color: #111; background: #fff; padding: 16px; }
        .print-body { max-width: 100%; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; font-size: 11px; }
        th { background: #f1f1f1; }
        @media print { body { padding: 0; } }
    </style>
    @stack('styles')
</head>
<body>
    <div class="print-body">
        @yield('content')
    </div>
</body>
</html>
