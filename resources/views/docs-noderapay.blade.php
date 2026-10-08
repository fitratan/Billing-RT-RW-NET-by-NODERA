<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dokumentasi &amp; Integrasi REST API — NODERA PAY</title>
  <meta name="description" content="Dokumentasi resmi NODERA PAY: Panduan integrasi pembayaran otomatis QRIS dinamis, Virtual Account, signature HMAC SHA-256, dan webhook notifikasi instan.">
  <meta name="keywords" content="dokumentasi nodera pay, api qris otomatis, dynamic qris api, payment gateway indonesia, integrasi mikhmon qris, webhook payment gateway">
  <meta name="author" content="NODERA - CV. Digital Network Solution">
  <meta name="robots" content="index, follow">

  <!-- Favicons -->
  
    <link rel="shortcut icon" href="/favicon.ico?v=37">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=37">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v=37">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=37">
<link href="/assets/img/nodera/logo.png" rel="apple-touch-icon">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Mukta:wght@200;300;400;500;600;700;800&family=Abel:wght@400&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="/assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="/assets/css/main.css" rel="stylesheet">

  <style>
    .tab-btn {
      padding: 6px 14px;
      font-size: 0.8rem;
      font-weight: 600;
      border-radius: 6px;
      color: #94a3b8;
      background: transparent;
      border: 1px solid transparent;
      cursor: pointer;
      transition: all 0.2s;
    }
    .tab-btn:hover {
      color: #fff;
      background: rgba(255, 255, 255, 0.05);
    }
    .tab-btn.active {
      color: #38bdf8;
      background: rgba(56, 189, 248, 0.12);
      border-color: rgba(56, 189, 248, 0.3);
    }
    .code-tab-content {
      display: none;
    }
    .code-tab-content.active {
      display: block;
    }
    .btn-copy {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.15);
      color: #cbd5e1;
      font-size: 0.75rem;
      padding: 4px 10px;
      border-radius: 6px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-copy:hover {
      background: rgba(56, 189, 248, 0.2);
      color: #fff;
      border-color: #38bdf8;
    }
  </style>
</head>

<body class="index-page docs-wrapper">

  <!-- Header -->
  <header id="header" class="header fixed-top d-flex align-items-center">
    <div class="container-fluid d-flex align-items-center justify-content-between px-3 px-md-4">

      <!-- Left: Brand Logo -->
      <a href="/" class="logo d-flex align-items-center">
        <img src="/assets/img/nodera/logo-white.png" alt="NODERA" style="max-height: 34px;">
        <h1 class="sitename ms-2" style="font-size: 1.35rem; font-weight: 700; letter-spacing: 0.5px; margin: 0;">NODERA</h1>
      </a>

      <!-- Center: Navigation Menu Selector -->
                              <nav id="navmenu" class="navmenu">
        <ul>
          <li><a href="/noderapay#hero">Beranda</a></li>
          <li class="dropdown"><a href="#"><span>Produk</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
            <ul>
              <li><a href="https://dgtlnetsolution.com/login" target="_blank"><i class="bi bi-receipt text-info me-2"></i><strong>NODERA BILLING</strong></a></li>
              <li><a href="/noderapay"><i class="bi bi-qr-code-scan text-info me-2"></i><strong>NODERA PAY</strong></a></li>
              <li><a href="/wagateway"><i class="bi bi-whatsapp text-info me-2"></i><strong>NODERA WHATSAPP</strong></a></li>
              <li><a href="https://panel.dgtlnetsolution.com" target="_blank"><i class="bi bi-cloud-check text-info me-2"></i><strong>NODERA CLOUD PANEL</strong></a></li>
            </ul>
          </li>
          <li class="dropdown"><a href="#" class="active"><span>Dokumentasi</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
            <ul>
              <li><a href="/noderapay/docs" class="active"><i class="bi bi-qr-code text-info me-2"></i><strong>Dokumentasi API</strong></a></li>
              <li><a href="/terms"><i class="bi bi-file-earmark-text text-info me-2"></i><strong>Syarat &amp; Ketentuan</strong></a></li>
              <li><a href="/privacy"><i class="bi bi-shield-check text-info me-2"></i><strong>Kebijakan Privasi</strong></a></li>
            </ul>
          </li>
          <li><a href="/#contact">Kontak</a></li>
        </ul>
      </nav>

      <!-- Right: Action Button & Mobile Toggle -->
      <div class="d-flex align-items-center gap-2">
        <a href="https://gateway.dgtlnetsolution.com/login" class="btn-get-started">
          <span>GET STARTED</span>
          <i class="bi bi-arrow-right"></i>
        </a>
        <i class="mobile-nav-toggle bi bi-list"></i>
      </div>

    </div>
  </header>

  <main class="main" style="padding-top: 100px; padding-bottom: 80px;">
    <div class="container px-3 px-sm-4">
      
      <!-- Docs Hero Banner -->
      <div class="p-4 p-md-5 rounded-4 mb-4" style="background: linear-gradient(135deg, #052A4E 0%, #0c3866 100%); border: 1px solid rgba(16, 163, 215, 0.4);" data-aos="fade-up">
        <div class="d-flex align-items-center gap-2 text-info mb-2 font-monospace" style="font-size: 0.85rem;">
          <i class="bi bi-code-slash"></i>
          <span>REST API REFERENCE V1 &mdash; NODERA PAY</span>
        </div>
        <h1 class="text-white fw-bold mb-2" style="font-size: 1.85rem;">Dokumentasi &amp; Integrasi REST API</h1>
        <p class="text-white-50 mb-0" style="font-size: 0.95rem;">Integrasikan pembayaran otomatis QRIS dan Virtual Account ke dalam Mikhmon, billing hotspot, sistem e-commerce, atau aplikasi kustom Anda dengan cepat dan aman.</p>
      </div>

      <div class="row">
        <!-- Main Content (Full Width) -->
        <div class="col-12">
          <div class="docs-content">

            <!-- Section 1: Base URL & Auth -->
            <section id="base-url" class="docs-section">
              <h2><i class="bi bi-globe text-info"></i> 1. URL Dasar &amp; IP Server Gateway</h2>
              <p>Base endpoint REST API dan alamat IP keluar resmi NODERA PAY.</p>
              
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <div class="p-3 rounded-3" style="background: #060b17; border: 1px solid rgba(255,255,255,0.08);">
                    <div class="text-uppercase text-secondary small fw-bold mb-2">Base Endpoint API</div>
                    <div class="d-flex align-items-center justify-content-between p-2 rounded" style="background: #090f21; border: 1px solid rgba(255,255,255,0.06);">
                      <code class="text-info fw-bold font-monospace" style="font-size: 0.82rem;">https://gateway.dgtlnetsolution.com/api/v1/noderapay</code>
                      <button class="btn-copy" onclick="navigator.clipboard.writeText('https://gateway.dgtlnetsolution.com/api/v1/noderapay'); this.innerHTML='<i class=\'bi bi-check2\'></i> Tersalin'; setTimeout(() => this.innerHTML='<i class=\'bi bi-copy\'></i> Salin', 2000);">
                        <i class="bi bi-copy"></i> Salin
                      </button>
                    </div>
                    <p class="text-secondary small mt-2 mb-0">Gunakan endpoint ini sebagai awalan seluruh route pemanggilan API.</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 rounded-3" style="background: #060b17; border: 1px solid rgba(255,255,255,0.08);">
                    <div class="text-uppercase text-secondary small fw-bold mb-2">IP Server NODERA PAY (Outbound)</div>
                    <div class="d-flex align-items-center justify-content-between p-2 rounded" style="background: #090f21; border: 1px solid rgba(255,255,255,0.06);">
                      <code class="text-white fw-bold font-monospace" style="font-size: 0.82rem;">127.0.0.1</code>
                      <button class="btn-copy" onclick="navigator.clipboard.writeText('127.0.0.1'); this.innerHTML='<i class=\'bi bi-check2\'></i> Tersalin'; setTimeout(() => this.innerHTML='<i class=\'bi bi-copy\'></i> Salin', 2000);">
                        <i class="bi bi-copy"></i> Salin
                      </button>
                    </div>
                    <p class="text-secondary small mt-2 mb-0">Daftarkan IP ini pada whitelist firewall/WAF server Anda untuk webhook.</p>
                  </div>
                </div>
              </div>

              <h3>Autentikasi &amp; Kredensial Request:</h3>
              <p>Metode autentikasi melalui Request Body atau HTTP Headers:</p>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <div class="p-3 rounded-3" style="background: #060b17; border: 1px solid rgba(255,255,255,0.08);">
                    <div class="text-uppercase text-secondary small fw-bold mb-1">Metode 1: Body Parameter</div>
                    <div class="code-box m-0">
                      <pre><code>{
  "code_merchant": "NP-YOURMERCHANT",
  "api_key": "np_live_your_api_key"
}</code></pre>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 rounded-3" style="background: #060b17; border: 1px solid rgba(255,255,255,0.08);">
                    <div class="text-uppercase text-secondary small fw-bold mb-1">Metode 2: HTTP Headers</div>
                    <div class="code-box m-0">
                      <pre><code>X-Merchant-Code: NP-YOURMERCHANT
X-Api-Key: np_live_your_api_key</code></pre>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <!-- Section 2: Create Transaction -->
            <section id="create-trx" class="docs-section">
              <h2><i class="bi bi-qr-code-scan text-info"></i> 2. Buat Transaksi Pembayaran</h2>
              <p>Gunakan endpoint ini untuk membuat tagihan pembayaran QRIS dinamis &amp; URL checkout.</p>
              
              <div class="endpoint-badge">
                <span class="method-tag method-post">POST</span>
                <span>https://gateway.dgtlnetsolution.com/api/v1/noderapay/create-transaction</span>
              </div>

              <h3>Parameter Request (JSON Body)</h3>
              <div class="table-responsive">
                <table class="param-table">
                  <thead>
                    <tr>
                      <th>Parameter</th>
                      <th>Tipe</th>
                      <th>Status</th>
                      <th>Deskripsi</th>
                      <th>Contoh</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><code>amount</code></td>
                      <td>integer</td>
                      <td><span class="badge bg-success">Wajib</span></td>
                      <td>Nominal transaksi (minimal Rp 1.000)</td>
                      <td><code>10000</code></td>
                    </tr>
                    <tr>
                      <td><code>ref_id</code></td>
                      <td>string</td>
                      <td><span class="badge bg-success">Wajib</span></td>
                      <td>ID unik transaksi dari aplikasi Anda (maks 100 karakter)</td>
                      <td><code>HOTSPOT-123456</code></td>
                    </tr>
                    <tr>
                      <td><code>customer_name</code></td>
                      <td>string</td>
                      <td><span class="badge bg-secondary">Opsional</span></td>
                      <td>Nama lengkap pembeli / pelanggan</td>
                      <td><code>Budi Santoso</code></td>
                    </tr>
                    <tr>
                      <td><code>customer_phone</code></td>
                      <td>string</td>
                      <td><span class="badge bg-secondary">Opsional</span></td>
                      <td>Nomor WhatsApp pembeli untuk kirim notifikasi/kuitansi</td>
                      <td><code>081234567890</code></td>
                    </tr>
                    <tr>
                      <td><code>notes</code></td>
                      <td>string</td>
                      <td><span class="badge bg-secondary">Opsional</span></td>
                      <td>Catatan transaksi / deskripsi produk / paket voucher</td>
                      <td><code>Voucher 1 Hari 5Mbps</code></td>
                    </tr>
                    <tr>
                      <td><code>callback_url</code></td>
                      <td>string</td>
                      <td><span class="badge bg-secondary">Opsional</span></td>
                      <td>Override URL webhook khusus untuk transaksi ini saja</td>
                      <td><code>https://domain.com/webhook</code></td>
                    </tr>
                    <tr>
                      <td><code>fee_bearer</code></td>
                      <td>string</td>
                      <td><span class="badge bg-secondary">Opsional</span></td>
                      <td>Penanggung fee: <code>CUSTOMER</code> (gross up) atau <code>MERCHANT</code></td>
                      <td><code>CUSTOMER</code></td>
                    </tr>
                    <tr>
                      <td><code>expired_time</code></td>
                      <td>integer</td>
                      <td><span class="badge bg-secondary">Opsional</span></td>
                      <td>Durasi kedaluwarsa QRIS dalam menit (default 15 menit)</td>
                      <td><code>15</code></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <h3>Contoh Kode Integrasi Multi-Bahasa:</h3>
              <div class="d-flex flex-wrap gap-2 mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                <button class="tab-btn active" onclick="switchCodeTab('php-mikhmon', this)">PHP Mikhmon / Native</button>
                <button class="tab-btn" onclick="switchCodeTab('curl', this)">cURL CLI</button>
                <button class="tab-btn" onclick="switchCodeTab('nodejs', this)">Node.js (Axios)</button>
                <button class="tab-btn" onclick="switchCodeTab('python', this)">Python (Requests)</button>
              </div>

              <div id="tab-php-mikhmon" class="code-tab-content active">
                <div class="code-box">
                  <pre><code>&lt;?php
$apiUrl       = "https://gateway.dgtlnetsolution.com/api/v1/noderapay/create-transaction";
$merchantCode = "NP-YOURMERCHANT";
$apiKey       = "np_live_your_api_key";

$data = [
    'code_merchant'  => $merchantCode,
    'api_key'        => $apiKey,
    'amount'         => 10000,
    'ref_id'         => 'HOTSPOT-' . time(),
    'customer_name'  => 'Pelanggan Hotspot',
    'customer_phone' => '081234567890',
    'notes'          => 'Voucher 1 Hari 5Mbps',
    'expired_time'   => 15,
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

if (isset($result['status']) && $result['status'] === 'success') {
    $qrUrl   = $result['data']['qr_url'];
    $payUrl  = $result['data']['checkout_url'];
    echo "Scan QRIS di: " . $qrUrl;
} else {
    echo "Gagal: " . ($result['message'] ?? 'Error');
}</code></pre>
                </div>
              </div>

              <div id="tab-curl" class="code-tab-content">
                <div class="code-box">
                  <pre><code>curl -X POST "https://gateway.dgtlnetsolution.com/api/v1/noderapay/create-transaction"   -H "Content-Type: application/json"   -H "Accept: application/json"   -d '{
    "code_merchant": "NP-YOURMERCHANT",
    "api_key": "np_live_your_api_key",
    "amount": 10000,
    "ref_id": "ORDER-987654",
    "customer_name": "Budi Santoso",
    "customer_phone": "081234567890",
    "notes": "Pembayaran Tagihan Internet",
    "expired_time": 15
  }'</code></pre>
                </div>
              </div>

              <div id="tab-nodejs" class="code-tab-content">
                <div class="code-box">
                  <pre><code>import axios from 'axios';

const createPayment = async () => {
  try {
    const res = await axios.post('https://gateway.dgtlnetsolution.com/api/v1/noderapay/create-transaction', {
      code_merchant: 'NP-YOURMERCHANT',
      api_key: 'np_live_your_api_key',
      amount: 10000,
      ref_id: `ORDER-${Date.now()}`,
      customer_name: 'Budi Santoso',
      customer_phone: '081234567890',
      notes: 'Pembayaran Tagihan',
      expired_time: 15
    });

    if (res.data.status === 'success') {
      console.log('QR Code URL:', res.data.data.qr_url);
      console.log('Checkout URL:', res.data.data.checkout_url);
    }
  } catch (err) {
    console.error('API Error:', err.response?.data || err.message);
  }
};

createPayment();</code></pre>
                </div>
              </div>

              <div id="tab-python" class="code-tab-content">
                <div class="code-box">
                  <pre><code>import requests

url = "https://gateway.dgtlnetsolution.com/api/v1/noderapay/create-transaction"
payload = {
    "code_merchant": "NP-YOURMERCHANT",
    "api_key": "np_live_your_api_key",
    "amount": 10000,
    "ref_id": "ORDER-123456",
    "customer_name": "Budi Santoso",
    "customer_phone": "081234567890",
    "notes": "Langganan Bulanan",
    "expired_time": 15
}
headers = {
    "Content-Type": "application/json",
    "Accept": "application/json"
}

res = requests.post(url, json=payload, headers=headers)
data = res.json()

if res.status_code == 200 and data.get('status') == 'success':
    print("QR Code URL:", data['data']['qr_url'])
    print("Checkout URL:", data['data']['checkout_url'])
else:
    print("Error:", data.get('message'))</code></pre>
                </div>
              </div>
            </section>

            <!-- Section 3: Check Status -->
            <section id="check-status" class="docs-section">
              <h2><i class="bi bi-clock-history text-info"></i> 3. Cek Status Transaksi</h2>
              <p>Periksa status pembayaran transaksi sewaktu-waktu menggunakan <code>trx_reference</code> atau <code>ref_id</code>.</p>
              
              <div class="endpoint-badge">
                <span class="method-tag method-get">GET</span>
                <span>https://gateway.dgtlnetsolution.com/api/v1/noderapay/get-status</span>
              </div>

              <h3>Parameter Query</h3>
              <div class="table-responsive">
                <table class="param-table">
                  <thead>
                    <tr>
                      <th>Parameter</th>
                      <th>Tipe</th>
                      <th>Status</th>
                      <th>Deskripsi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><code>trx_reference</code></td>
                      <td>string</td>
                      <td>Wajib (atau ref_id)</td>
                      <td>Nomor referensi transaksi dari NODERA PAY</td>
                    </tr>
                    <tr>
                      <td><code>ref_id</code></td>
                      <td>string</td>
                      <td>Wajib (atau trx_reference)</td>
                      <td>Kode referensi transaksi Anda saat create transaction</td>
                    </tr>
                    <tr>
                      <td><code>code_merchant</code></td>
                      <td>string</td>
                      <td>Wajib</td>
                      <td>Kode merchant akun Anda</td>
                    </tr>
                    <tr>
                      <td><code>api_key</code></td>
                      <td>string</td>
                      <td>Wajib</td>
                      <td>API key akun Anda (dapat dikirim di query atau header <code>X-Api-Key</code>)</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="code-box">
                <pre><code>// Request URL
GET https://gateway.dgtlnetsolution.com/api/v1/noderapay/get-status?code_merchant=NP-YOURMERCHANT&api_key=np_live_your_api_key&trx_reference=NP-20261004-8912301

// Response (Lunas)
{
  "status": "success",
  "data": {
    "trx_reference": "NP-20261004-8912301",
    "ref_id": "HOTSPOT-123456",
    "amount": 10000,
    "fee": 70,
    "status": "PAID",
    "payment_method": "QRIS",
    "paid_at": "2026-10-04 15:32:10"
  }
}</code></pre>
              </div>
            </section>

            <!-- Section 4: Webhook & Signature -->
            <section id="webhook" class="docs-section">
              <h2><i class="bi bi-broadcast text-info"></i> 4. Webhook Notifikasi &amp; Verifikasi Signature</h2>
              <p>Notifikasi HTTP POST otomatis seketika pembayaran selesai diverifikasi.</p>
              
              <div class="p-3 rounded-3 mb-3" style="background: #060b17; border: 1px solid rgba(255,255,255,0.08);">
                <div class="text-uppercase text-secondary small fw-bold mb-1">Formula Keamanan Signature (HMAC SHA-256)</div>
                <code class="text-info font-monospace small">signature = hash('sha256', $trx_reference . $ref_id . $amount . $api_key)</code>
              </div>

              <h3>Contoh Handler Webhook (PHP):</h3>
              <div class="code-box">
                <pre><code>&lt;?php
$apiKey = "np_live_your_api_key";

$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true);

if (!$payload) {
    http_response_code(400);
    exit(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$trxRef     = $payload['trx_reference'] ?? '';
$refId      = $payload['ref_id'] ?? '';
$amount     = $payload['amount'] ?? 0;
$signature  = $payload['signature'] ?? '';

$expectedSignature = hash('sha256', $trxRef . $refId . $amount . $apiKey);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Invalid Signature']));
}

if (($payload['status'] ?? '') === 'paid') {
    // Pembayaran Sukses! Aktifkan voucher atau perpanjang masa langganan pelanggan
}

http_response_code(200);
echo json_encode(['status' => 'ok']);</code></pre>
              </div>
            </section>

            <!-- Section 5: Status Code & Terms -->
            <section id="terms" class="docs-section">
              <h2><i class="bi bi-shield-check text-info"></i> 5. Syarat &amp; Ketentuan Layanan (Terms of Service)</h2>
              <p>Ketentuan resmi pendaftaran merchant, skema biaya, penarikan dana, dan batasan penggunaan.</p>

              <div class="row g-3">
                <div class="col-md-6">
                  <div class="p-3 rounded-3" style="background: #060b17; border: 1px solid rgba(255,255,255,0.08);">
                    <h4 class="text-white fw-bold h6 mb-2">Pendaftaran &amp; Akun Merchant (No-KYC Onboarding)</h4>
                    <p class="text-secondary small mb-0">Pendaftaran akun merchant di NODERA PAY tidak memerlukan pengunggahan identitas pribadi (KTP/Passport) atau berkas legalitas perusahaan. Akun langsung aktif beserta kredensial Merchant Code dan API Key siap pakai.</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 rounded-3" style="background: #060b17; border: 1px solid rgba(255,255,255,0.08);">
                    <h4 class="text-white fw-bold h6 mb-2">Struktur Biaya Transaksi (Transparent Fee)</h4>
                    <p class="text-secondary small mb-0"><strong>QRIS Dinamis:</strong> Biaya transaksi 0.7% per transaksi berhasil.<br><strong>Virtual Account:</strong> Biaya transaksi Rp 2.500 per transaksi berhasil.<br>Biaya dipotong secara otomatis dari nominal transaksi masuk tanpa biaya bulanan.</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 rounded-3" style="background: #060b17; border: 1px solid rgba(255,255,255,0.08);">
                    <h4 class="text-white fw-bold h6 mb-2">Penarikan Saldo &amp; Settlement (Payouts)</h4>
                    <p class="text-secondary small mb-0">Merchant dapat mencairkan saldo hasil transaksi kapan saja ke seluruh rekening bank nasional dan dompet digital (e-wallet) di Indonesia melalui menu Penarikan Saldo di dashboard merchant.</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 rounded-3" style="background: #060b17; border: 1px solid rgba(255,255,255,0.08);">
                    <h4 class="text-white fw-bold h6 mb-2">Aktivitas &amp; Bisnis yang Dilarang Keras</h4>
                    <p class="text-secondary small mb-0">Dilarang keras untuk perjudian online, pornografi, penipuan/phishing, skema piramida/ponzi, narkotika, dan perdagangan senjata api. Pelanggaran berakibat pemblokiran permanen akun &amp; saldo.</p>
                  </div>
                </div>
              </div>
            </section>

          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- Footer -->
  <footer id="footer" class="footer position-relative light-background border-top border-secondary border-opacity-25" style="background: #040812 !important;">
    <div class="container footer-top px-3 px-sm-4">
      <div class="row gy-4">
        <div class="col-lg-5 col-md-12 footer-about">
          <a href="/" class="logo d-flex align-items-center mb-3">
            <img src="/assets/img/nodera/logo-white.png" alt="NODERA" style="max-height: 32px;">
            <span class="sitename ms-2 text-white" style="font-size: 1.25rem; font-weight: 700;">NODERA</span>
          </a>
          </div>

        <div class="col-lg-3 col-6 footer-links">
          <h4 class="text-white">Ekosistem Produk</h4>
          <ul>
            <li><a href="https://dgtlnetsolution.com/login" target="_blank">NODERA BILLING</a></li>
            <li><a href="/noderapay">NODERA PAY (QRIS Gateway)</a></li>
            <li><a href="/wagateway">NODERA WHATSAPP (Messaging API)</a></li>
            <li><a href="https://panel.dgtlnetsolution.com" target="_blank">NODERA CLOUD PANEL</a></li>
          </ul>
        </div>

        <div class="col-lg-4 col-md-12 footer-contact">
          <h4 class="text-white">Legalitas &amp; Kantor Resmi</h4>
          <p class="text-secondary small mb-1"><strong>Badan Usaha:</strong> CV. Digital Network Solution</p>
          <p class="text-secondary small mb-1"><strong>Alamat:</strong> Pareyaan, Jl. Cempaka II, Krajan Timur, Sumber Kolak, Panarukan, Situbondo, Jawa Timur 68351</p>
          <p class="text-secondary small mb-1"><strong>WhatsApp:</strong> 0851-5517-3547</p>
          <p class="text-secondary small"><strong>Email:</strong> fitrandeso22@gmail.com</p>
        </div>
      </div>
    </div>

    <div class="container copyright text-center mt-4 pt-4 border-top border-secondary border-opacity-10">
      <p class="text-secondary small mb-0">&copy; 2026 <strong class="text-white">NODERA</strong>. Hak Cipta Dilindungi Undang-Undang. Dikembangkan dan dikelola oleh <strong class="text-white">CV. Digital Network Solution</strong>.</p>
    </div>
  </footer>

  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/vendor/aos/aos.js"></script>
  <script src="/assets/js/main.js"></script>

  <script>
    function switchCodeTab(tabId, btn) {
      document.querySelectorAll('.code-tab-content').forEach(el => el.classList.remove('active'));
      document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
      const target = document.getElementById('tab-' + tabId);
      if (target) target.classList.add('active');
      if (btn) btn.classList.add('active');
    }
  </script>

</body>
</html>