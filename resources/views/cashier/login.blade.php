<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login Kasir</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>body{background:#f3f4f6;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
.card{border:none;border-radius:20px;box-shadow:0 4px 24px rgba(0,0,0,.06);padding:32px;width:100%;max-width:400px;}
</style></head>
<body>
<div class="card">
    <div class="text-center mb-4"><i class="bi bi-cash-stack" style="font-size:2rem;color:#2563eb;"></i>
        <h5 class="fw-bold mt-2">Kasir</h5>
        <p class="text-muted small mb-0">Login untuk memulai sesi kasir</p>
    </div>
    @if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
    @if(session('msg'))<div class="alert alert-success py-2 small">{{ session('msg') }}</div>@endif
    <form method="POST">
        @csrf
        <div class="mb-3"><label class="form-label small fw-medium">Username</label>
            <input type="text" name="username" required class="form-control"></div>
        <div class="mb-3"><label class="form-label small fw-medium">Password</label>
            <input type="password" name="password" required class="form-control"></div>
        <button type="submit" class="btn btn-primary w-100 rounded-3 py-2 fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i> Buka Sesi</button>
    </form>
</div>
</body></html>
