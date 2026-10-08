<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dokumentasi REST API WhatsApp Gateway — NODERA WHATSAPP</title>
  <meta name="description" content="Panduan lengkap integrasi kirim pesan WhatsApp teks, media dokumen PDF/gambar, broadcast, pesan terjadwal, skrip MikroTik Netwatch, dan webhook notifikasi.">
  <meta name="keywords" content="dokumentasi wa gateway, api whatsapp indonesia, rest api whatsapp, whatsapp gateway mikrotik, webhook whatsapp, broadcast wa api">
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
          <li><a href="/wagateway#hero">Beranda</a></li>
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
              <li><a href="/wagateway/docs" class="active"><i class="bi bi-whatsapp text-info me-2"></i><strong>Dokumentasi API</strong></a></li>
              <li><a href="/terms"><i class="bi bi-file-earmark-text text-info me-2"></i><strong>Syarat &amp; Ketentuan</strong></a></li>
              <li><a href="/privacy"><i class="bi bi-shield-check text-info me-2"></i><strong>Kebijakan Privasi</strong></a></li>
            </ul>
          </li>
          <li><a href="/#contact">Kontak</a></li>
        </ul>
      </nav>

      <!-- Right: Action Button & Mobile Toggle -->
      <div class="d-flex align-items-center gap-2">
        <a href="https://wa.dgtlnetsolution.com/login" class="btn-get-started">
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
          <span>REST API v1.0 Universal Engine &mdash; NODERA WHATSAPP</span>
        </div>
        <h1 class="text-white fw-bold mb-2" style="font-size: 1.85rem;">Dokumentasi REST API WhatsApp Gateway</h1>
        <p class="text-white-50 mb-0" style="font-size: 0.95rem;">Panduan lengkap integrasi kirim pesan WhatsApp teks (Rp 20/pesan), media dokumen PDF/gambar (Rp 50/pesan), broadcast multi-kontak, pesan terjadwal, dan webhook ke sistem Anda.</p>
      </div>

      <div class="row">
        <!-- Main Content (Full Width) -->
        <div class="col-12">
          <div class="docs-content">

            <!-- Section 1: Base URL & Auth -->
            <section id="base-url" class="docs-section">
              <h2><i class="bi bi-globe text-info"></i> Production Base URL &amp; Header Autentikasi</h2>
              <p>Endpoint utama untuk seluruh pemanggilan REST API WhatsApp Gateway.</p>
              
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <div class="p-3 rounded-3" style="background: #060b17; border: 1px solid rgba(255,255,255,0.08);">
                    <div class="text-uppercase text-secondary small fw-bold mb-2">Production Base URL</div>
                    <div class="d-flex align-items-center justify-content-between p-2 rounded" style="background: #090f21; border: 1px solid rgba(255,255,255,0.06);">
                      <code class="text-info fw-bold font-monospace" style="font-size: 0.82rem;">https://dgtlnetsolution.com/api</code>
                      <button class="btn-copy" onclick="navigator.clipboard.writeText('https://dgtlnetsolution.com/api'); this.innerHTML='<i class=\'bi bi-check2\'></i> Tersalin'; setTimeout(() => this.innerHTML='<i class=\'bi bi-copy\'></i> Salin', 2000);">
                        <i class="bi bi-copy"></i> Salin
                      </button>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 rounded-3" style="background: #060b17; border: 1px solid rgba(255,255,255,0.08);">
                    <div class="text-uppercase text-secondary small fw-bold mb-2">Header Autentikasi</div>
                    <div class="d-flex align-items-center justify-content-between p-2 rounded" style="background: #090f21; border: 1px solid rgba(255,255,255,0.06);">
                      <code class="text-white fw-bold font-monospace" style="font-size: 0.82rem;">Authorization: Bearer dgtl_sec_YOUR_API_KEY_HERE</code>
                      <button class="btn-copy" onclick="navigator.clipboard.writeText('Authorization: Bearer dgtl_sec_YOUR_API_KEY_HERE'); this.innerHTML='<i class=\'bi bi-check2\'></i> Tersalin'; setTimeout(() => this.innerHTML='<i class=\'bi bi-copy\'></i> Salin', 2000);">
                        <i class="bi bi-copy"></i> Salin
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <h3>Pilihan Bahasa Pemrograman:</h3>
              <div class="d-flex flex-wrap gap-2 mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                <button class="tab-btn active" onclick="switchCodeTab('curl-demo', this)">CURL</button>
                <button class="tab-btn" onclick="switchCodeTab('php-demo', this)">PHP</button>
                <button class="tab-btn" onclick="switchCodeTab('nodejs-demo', this)">NODEJS</button>
                <button class="tab-btn" onclick="switchCodeTab('python-demo', this)">PYTHON</button>
              </div>

              <div id="tab-curl-demo" class="code-tab-content active">
                <div class="code-box">
                  <pre><code>curl -X POST "https://dgtlnetsolution.com/api/message/send"   -H "Authorization: Bearer dgtl_sec_YOUR_API_KEY_HERE"   -H "Content-Type: application/json"   -d '{
    "phone": "081234567890",
    "message": "Halo! Tagihan internet Anda sebesar Rp 150.000 telah terbit. Silakan bayar via: https://gateway.dgtlnetsolution.com/pay/INV123"
  }'</code></pre>
                </div>
              </div>

              <div id="tab-php-demo" class="code-tab-content">
                <div class="code-box">
                  <pre><code>&lt;?php
$ch = curl_init("https://dgtlnetsolution.com/api/message/send");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer dgtl_sec_YOUR_API_KEY_HERE",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "phone"   => "081234567890",
    "message" => "Halo! Tagihan internet Anda sebesar Rp 150.000 telah terbit."
]));
$res = curl_exec($ch);
curl_close($ch);
print_r(json_decode($res, true));</code></pre>
                </div>
              </div>

              <div id="tab-nodejs-demo" class="code-tab-content">
                <div class="code-box">
                  <pre><code>import axios from 'axios';

const send = async () => {
  const res = await axios.post('https://dgtlnetsolution.com/api/message/send', {
    phone: '081234567890',
    message: 'Halo! Tagihan internet Anda sebesar Rp 150.000 telah terbit.'
  }, {
    headers: { 'Authorization': 'Bearer dgtl_sec_YOUR_API_KEY_HERE' }
  });
  console.log(res.data);
};
send();</code></pre>
                </div>
              </div>

              <div id="tab-python-demo" class="code-tab-content">
                <div class="code-box">
                  <pre><code>import requests

url = "https://dgtlnetsolution.com/api/message/send"
payload = {
    "phone": "081234567890",
    "message": "Halo! Tagihan internet Anda sebesar Rp 150.000 telah terbit."
}
headers = {"Authorization": "Bearer dgtl_sec_YOUR_API_KEY_HERE"}
res = requests.post(url, json=payload, headers=headers)
print(res.json())</code></pre>
                </div>
              </div>
            </section>

            <!-- Section 2: Send Text -->
            <section id="send-text" class="docs-section">
              <h2><i class="bi bi-chat-dots text-info"></i> 1. Kirim Pesan Teks (Send Text)</h2>
              <p>Mengirimkan pesan teks personal atau notifikasi langsung ke kontak WhatsApp tujuan.</p>
              
              <div class="endpoint-badge">
                <span class="method-tag method-post">POST</span>
                <span>/api/message/send &nbsp;atau&nbsp; /api/message/send-text</span>
              </div>

              <h3>Parameter Request</h3>
              <div class="table-responsive">
                <table class="param-table">
                  <thead>
                    <tr>
                      <th>Parameter</th>
                      <th>Tipe</th>
                      <th>Wajib</th>
                      <th>Deskripsi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><code>phone</code> / <code>to</code></td>
                      <td>string</td>
                      <td>Ya</td>
                      <td>Nomor tujuan WhatsApp (format 08... atau 628... dinormalisasi otomatis).</td>
                    </tr>
                    <tr>
                      <td><code>message</code> / <code>text</code></td>
                      <td>string</td>
                      <td>Ya</td>
                      <td>Isi teks pesan WhatsApp yang akan dikirimkan.</td>
                    </tr>
                    <tr>
                      <td><code>device_session</code></td>
                      <td>string</td>
                      <td>Opsional</td>
                      <td>Session device spesifik yang ingin digunakan untuk mengirim.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            <!-- Section 3: Send Media -->
            <section id="send-media" class="docs-section">
              <h2><i class="bi bi-file-earmark-pdf text-info"></i> 2. Kirim Pesan Media (Gambar, Dokumen PDF, Video, Audio)</h2>
              <p>Mengirimkan berkas lampiran faktur invoice PDF, bukti bayar gambar, atau dokumen resmi.</p>
              
              <div class="endpoint-badge">
                <span class="method-tag method-post">POST</span>
                <span>/api/message/send-media</span>
              </div>

              <h3>Parameter Request</h3>
              <div class="table-responsive">
                <table class="param-table">
                  <thead>
                    <tr>
                      <th>Parameter</th>
                      <th>Tipe</th>
                      <th>Wajib</th>
                      <th>Deskripsi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><code>phone</code> / <code>to</code></td>
                      <td>string</td>
                      <td>Ya</td>
                      <td>Nomor tujuan WhatsApp.</td>
                    </tr>
                    <tr>
                      <td><code>media_url</code></td>
                      <td>string (URL)</td>
                      <td>Ya</td>
                      <td>URL publik file gambar (.jpg/.png), PDF (.pdf), dokumen, video (.mp4), atau audio.</td>
                    </tr>
                    <tr>
                      <td><code>caption</code> / <code>message</code></td>
                      <td>string</td>
                      <td>Opsional</td>
                      <td>Keterangan teks yang menyertai file media.</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="code-box">
                <pre><code>curl -X POST "https://dgtlnetsolution.com/api/message/send-media"   -H "Authorization: Bearer dgtl_sec_YOUR_API_KEY_HERE"   -H "Content-Type: application/json"   -d '{
    "phone": "081234567890",
    "media_url": "https://domainanda.com/invoices/INV-2026-001.pdf",
    "caption": "Berikut lampiran invoice tagihan internet Anda.",
    "media_type": "document"
  }'</code></pre>
              </div>
            </section>

            <!-- Section 4: Broadcast -->
            <section id="broadcast" class="docs-section">
              <h2><i class="bi bi-megaphone text-info"></i> 3. Kirim Broadcast Massal (Multi-Kontak)</h2>
              <p>Engine broadcast dilengkapi jeda dinamis anti-banned antar nomor tujuan.</p>
              
              <div class="endpoint-badge">
                <span class="method-tag method-post">POST</span>
                <span>/api/message/broadcast &nbsp;atau&nbsp; /api/broadcast</span>
              </div>

              <h3>Parameter Request</h3>
              <div class="table-responsive">
                <table class="param-table">
                  <thead>
                    <tr>
                      <th>Parameter</th>
                      <th>Tipe</th>
                      <th>Wajib</th>
                      <th>Deskripsi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><code>recipients</code></td>
                      <td>array | string</td>
                      <td>Ya</td>
                      <td>Array daftar nomor telepon tujuan (contoh: ["0812...", "0898..."]).</td>
                    </tr>
                    <tr>
                      <td><code>message</code></td>
                      <td>string</td>
                      <td>Ya</td>
                      <td>Isi teks pengumuman broadcast.</td>
                    </tr>
                    <tr>
                      <td><code>delay_seconds</code></td>
                      <td>integer</td>
                      <td>Opsional</td>
                      <td>Jeda acak anti-banned antar pesan (default: 3-5 detik).</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="code-box">
                <pre><code>curl -X POST "https://dgtlnetsolution.com/api/message/broadcast"   -H "Authorization: Bearer dgtl_sec_YOUR_API_KEY_HERE"   -H "Content-Type: application/json"   -d '{
    "recipients": ["081234567890", "089876543210", "085611223344"],
    "message": "Pemberitahuan: Pemeliharaan jaringan backbone pada pukul 01:00 - 04:00 WIB.",
    "delay_seconds": 3
  }'</code></pre>
              </div>
            </section>

            <!-- Section 5: Schedule -->
            <section id="schedule" class="docs-section">
              <h2><i class="bi bi-calendar-event text-info"></i> 4. Pesan Terjadwal (Scheduled Message)</h2>
              <p>Jadwalkan pengiriman pesan otomatis di waktu tertentu di masa mendatang.</p>
              
              <div class="endpoint-badge">
                <span class="method-tag method-post">POST</span>
                <span>/api/message/schedule &nbsp;atau&nbsp; /api/schedule</span>
              </div>

              <h3>Parameter Request</h3>
              <div class="table-responsive">
                <table class="param-table">
                  <thead>
                    <tr>
                      <th>Parameter</th>
                      <th>Tipe</th>
                      <th>Wajib</th>
                      <th>Deskripsi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><code>phone</code> / <code>to</code></td>
                      <td>string</td>
                      <td>Ya</td>
                      <td>Nomor tujuan WhatsApp penerima.</td>
                    </tr>
                    <tr>
                      <td><code>message</code></td>
                      <td>string</td>
                      <td>Ya</td>
                      <td>Isi pesan WhatsApp yang akan dikirim pada waktu yang ditentukan.</td>
                    </tr>
                    <tr>
                      <td><code>scheduled_at</code></td>
                      <td>string (Datetime)</td>
                      <td>Ya</td>
                      <td>Waktu eksekusi pengiriman otomatis (format: YYYY-MM-DD HH:mm:ss).</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="code-box">
                <pre><code>curl -X POST "https://dgtlnetsolution.com/api/message/schedule"   -H "Authorization: Bearer dgtl_sec_YOUR_API_KEY_HERE"   -H "Content-Type: application/json"   -d '{
    "phone": "081234567890",
    "message": "Pengingat: Tagihan internet Anda jatuh tempo besok pagi.",
    "scheduled_at": "2026-10-05 08:00:00"
  }'</code></pre>
              </div>
            </section>

            <!-- Section 6: Check Quota -->
            <section id="quota" class="docs-section">
              <h2><i class="bi bi-wallet2 text-info"></i> 5. Cek Saldo &amp; Kuota Akun (Check Quota &amp; Balance)</h2>
              <p>Periksa sisa kuota kirim pesan gratis atau saldo kuota akun gateway Anda.</p>
              
              <div class="endpoint-badge">
                <span class="method-tag method-get">GET</span>
                <span>/api/quota</span>
              </div>

              <div class="code-box">
                <pre><code>// Response (HTTP 200 OK)
{
  "status": "success",
  "tier": "Free Tier",
  "remaining_quota": 484,
  "balance": 0
}</code></pre>
              </div>
            </section>

            <!-- Section 7: Inbound Webhook -->
            <section id="webhook" class="docs-section">
              <h2><i class="bi bi-arrow-left-right text-info"></i> 6. Inbound Webhook &amp; Callback Event</h2>
              <p>Menerima pesan masuk &amp; pembaruan status pesan secara real-time ke URL webhook Anda.</p>
              
              <div class="code-box">
                <pre><code>{
  "event": "message.received",
  "from": "081234567890",
  "from_name": "Budi Santoso",
  "message_id": "WA-MSG-99881122",
  "type": "text",
  "text": "Bantuan internet lemot",
  "timestamp": "2026-10-04T12:00:00Z"
}</code></pre>
              </div>
            </section>

            <!-- Section 8: Universal Integration & MikroTik -->
            <section id="integration" class="docs-section">
              <h2><i class="bi bi-router text-info"></i> 7. Integrasi Aplikasi, MikroTik &amp; Sistem Universal</h2>
              <p>Kirim notifikasi otomatis dari ERP, CRM, E-Commerce, POS, Bot, atau skrip Netwatch MikroTik.</p>
              
              <div class="code-box">
                <pre><code># ==========================================
# SKRIP MIKROTIK NETWATCH (RouterOS v6 & v7)
# ==========================================
:local waToken "dgtl_sec_YOUR_API_KEY_HERE";
:local waPhone "081234567890";
:local hostName [/system identity get name];
:local msg ("🚨 *ALERT: LINK DOWN* %0A%0ARouter: " . $hostName . "%0ALink: Backbone Fiber Utama%0AWaktu: " . [/system clock get time] . " " . [/system clock get date]);

/tool fetch http-method=post   http-header-field="Authorization: Bearer $waToken,Content-Type: application/json"   http-data="{"phone":"$waPhone","message":"$msg"}"   url="https://dgtlnetsolution.com/api/message/send"   output=none;</code></pre>
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