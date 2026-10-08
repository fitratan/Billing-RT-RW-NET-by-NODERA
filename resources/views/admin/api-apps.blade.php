@extends('layouts.admin')
@section('title', 'API Apps')
@section('content')
<div class="container-fluid px-0">
    <h1 class="fs-4 fw-bold text-dark mb-4">API Apps</h1>

    

    {{-- Webhook Info --}}
    <div class="card shadow-sm rounded-3 border-0 mb-4">
        <div class="card-body">
            <h6 class="fw-semibold text-secondary mb-3 d-flex align-items-center gap-2"><i class="bi bi-link-45deg text-primary"></i> Webhook URLs</h6>
            <div class="d-flex flex-column gap-2">
                <div class="d-flex align-items-center justify-content-between p-2 px-3 bg-light rounded-3">
                    <span class="small text-muted">WhatsApp</span>
                    <code class="small text-primary">{{ $webhookUrls['whatsapp'] ?? '-' }}</code>
                </div>
                <div class="d-flex align-items-center justify-content-between p-2 px-3 bg-light rounded-3">
                    <span class="small text-muted">Payment</span>
                    <code class="small text-primary">{{ $webhookUrls['payment'] ?? '-' }}</code>
                </div>
                <div class="d-flex align-items-center justify-content-between p-2 px-3 bg-light rounded-3">
                    <span class="small text-muted">Midtrans</span>
                    <code class="small text-primary">{{ $webhookUrls['midtrans'] ?? '-' }}</code>
                </div>
                <div class="d-flex align-items-center justify-content-between p-2 px-3 bg-light rounded-3">
                    <span class="small text-muted">Telegram</span>
                    <code class="small text-primary">{{ $webhookUrls['telegram'] ?? '-' }}</code>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="/admin/my-settings/save">
        @csrf

        <div class="card shadow-sm rounded-3 border-0 mb-4">
            <div class="card-body">
                <h6 class="fw-semibold text-secondary mb-3 d-flex align-items-center gap-2"><i class="bi bi-send text-primary"></i> GenieACS</h6>
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <input type="text" name="GENIEACS_URL" value="{{ $GENIEACS_URL ?? '' }}" placeholder="URL" class="form-control">
                    </div>
                    <div class="col-12 col-lg-4">
                        <input type="text" name="GENIEACS_USERNAME" value="{{ $GENIEACS_USERNAME ?? '' }}" placeholder="Username" class="form-control">
                    </div>
                    <div class="col-12 col-lg-4">
                        <input type="password" name="GENIEACS_PASSWORD" value="{{ $GENIEACS_PASSWORD ?? '' }}" placeholder="Password" class="form-control">
                    </div>
                </div>
                <div class="mt-3">
                    <input type="text" name="GENIEACS_TOKEN" value="{{ $GENIEACS_TOKEN ?? '' }}" placeholder="Bearer Token (opsional)" class="form-control">
                </div>
            </div>
        </div>

        <div class="card shadow-sm rounded-3 border-0 mb-4">
            <div class="card-body">
                <h6 class="fw-semibold text-secondary mb-3 d-flex align-items-center gap-2"><i class="bi bi-whatsapp text-primary"></i> WhatsApp</h6>
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <input type="text" name="WHATSAPP_API_URL" value="{{ $WHATSAPP_API_URL ?? '' }}" placeholder="API URL" class="form-control">
                    </div>
                    <div class="col-12 col-lg-6">
                        <input type="text" name="WHATSAPP_TOKEN" value="{{ $WHATSAPP_TOKEN ?? '' }}" placeholder="Token" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm rounded-3 border-0 mb-4">
            <div class="card-body">
                <h6 class="fw-semibold text-secondary mb-3 d-flex align-items-center gap-2"><i class="bi bi-credit-card text-primary"></i> Tripay</h6>
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <input type="text" name="TRIPAY_API_KEY" value="{{ $TRIPAY_API_KEY ?? '' }}" placeholder="API Key" class="form-control">
                    </div>
                    <div class="col-12 col-lg-4">
                        <input type="text" name="TRIPAY_PRIVATE_KEY" value="{{ $TRIPAY_PRIVATE_KEY ?? '' }}" placeholder="Private Key" class="form-control">
                    </div>
                    <div class="col-12 col-lg-4">
                        <input type="text" name="TRIPAY_MERCHANT_CODE" value="{{ $TRIPAY_MERCHANT_CODE ?? '' }}" placeholder="Merchant Code" class="form-control">
                    </div>
                </div>
                <div class="mt-3">
                    <select name="TRIPAY_MODE" class="form-select">
                        <option value="sandbox" {{ ($TRIPAY_MODE ?? 'sandbox') == 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                        <option value="production" {{ ($TRIPAY_MODE ?? '') == 'production' ? 'selected' : '' }}>Production</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card shadow-sm rounded-3 border-0 mb-4">
            <div class="card-body">
                <h6 class="fw-semibold text-secondary mb-3 d-flex align-items-center gap-2"><i class="bi bi-credit-card-2-front text-primary"></i> Midtrans</h6>
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <input type="text" name="MIDTRANS_SERVER_KEY" value="{{ $MIDTRANS_SERVER_KEY ?? '' }}" placeholder="Server Key" class="form-control">
                    </div>
                    <div class="col-12 col-lg-4">
                        <input type="text" name="MIDTRANS_CLIENT_KEY" value="{{ $MIDTRANS_CLIENT_KEY ?? '' }}" placeholder="Client Key" class="form-control">
                    </div>
                    <div class="col-12 col-lg-4">
                        <select name="MIDTRANS_MODE" class="form-select">
                            <option value="sandbox" {{ ($MIDTRANS_MODE ?? 'sandbox') == 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                            <option value="production" {{ ($MIDTRANS_MODE ?? '') == 'production' ? 'selected' : '' }}>Production</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm rounded-3 border-0 mb-4">
            <div class="card-body">
                <h6 class="fw-semibold text-secondary mb-3 d-flex align-items-center gap-2"><i class="bi bi-telegram text-primary"></i> Telegram</h6>
                <div class="d-flex flex-column gap-3">
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <input type="text" name="TELEGRAM_BOT_TOKEN" value="{{ $TELEGRAM_BOT_TOKEN ?? '' }}" placeholder="Bot Token" class="form-control">
                        </div>
                        <div class="col-12 col-lg-6">
                            <input type="text" name="TELEGRAM_ADMIN_CHAT_IDS" value="{{ $TELEGRAM_ADMIN_CHAT_IDS ?? '' }}" placeholder="Admin Chat IDs (pisahkan dengan koma)" class="form-control">
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST" action="/admin/my-settings/setTelegramWebhook">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm rounded-3">
                                <i class="bi bi-link-45deg me-1"></i> Set Webhook
                            </button>
                        </form>
                        <form method="POST" action="/admin/my-settings/deleteTelegramWebhook">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-3">
                                <i class="bi bi-trash me-1"></i> Hapus Webhook
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 rounded-3 fw-semibold shadow-sm py-3">
            <i class="bi bi-floppy me-2"></i> Simpan Pengaturan API
        </button>
    </form>
</div>
@endsection