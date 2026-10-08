<!DOCTYPE html>
<html lang="id" dir="ltr">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pesanan #{{ $order->order_number }} | {{ $company['name'] ?? 'NODERA' }}</title>
    <link rel="icon" type="image/png" href="/favicon.png?v=37">
    
    <!-- Stylesheets from TITAN Template -->
    <link href="/titan/assets/lib/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto+Condensed:400,700,800" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800" rel="stylesheet">
    <link href="/titan/assets/lib/components-font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <link href="/titan/assets/css/style.css" rel="stylesheet">

    <style>
      body, html { background-color: #070B11 !important; color: #94A3B8; font-family: 'Open Sans', sans-serif; }
      
      .order-success-card {
        background: #0B111E;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 24px;
        padding: 30px 24px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
        max-width: 620px;
        margin: 30px auto;
        color: #CBD5E1;
      }
      
      .success-icon-box {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: rgba(16, 185, 129, 0.15);
        border: 2px solid #10B981;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px auto;
        color: #10B981;
        font-size: 32px;
        animation: pulseIcon 2s infinite;
      }
      .pending-icon-box {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: rgba(0, 194, 255, 0.15);
        border: 2px solid #00C2FF;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px auto;
        color: #00C2FF;
        font-size: 32px;
      }
      @keyframes pulseIcon {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
        70% { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
      }

      .order-num-badge {
        display: inline-block;
        background: rgba(0, 229, 255, 0.12);
        color: #00E5FF;
        border: 1px solid rgba(0, 229, 255, 0.3);
        padding: 5px 14px;
        border-radius: 20px;
        font-family: monospace;
        font-size: 14px;
        font-weight: 700;
        margin: 8px 0 16px 0;
      }

      .order-summary-table {
        width: 100%;
        margin: 16px 0;
        border-collapse: collapse;
      }
      .order-summary-table th {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 6px 4px;
        font-size: 11px;
        color: #94A3B8;
        text-transform: uppercase;
      }
      .order-summary-table td {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        padding: 8px 4px;
        font-size: 12px;
        color: #E2E8F0;
      }

      .bank-box {
        background: #0E1626;
        border: 1px solid rgba(0, 229, 255, 0.25);
        border-radius: 10px;
        padding: 10px 14px;
        margin-bottom: 8px;
        text-align: left;
      }
      .bank-box-title { font-size: 13px; font-weight: 800; color: #fff; }
      .bank-box-acc { font-family: monospace; font-size: 16px; color: #00E5FF; font-weight: 700; }
      .btn-copy {
        background: rgba(0, 229, 255, 0.15);
        border: 1px solid rgba(0, 229, 255, 0.3);
        color: #00E5FF;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
      }
      .btn-copy:hover {
        background: rgba(0, 229, 255, 0.3);
        color: #fff;
      }

      .qris-fintech-box {
        background: #0D1424;
        border: 1px solid rgba(0, 194, 255, 0.3);
        border-radius: 16px;
        padding: 18px;
        margin-top: 16px;
        text-align: center;
      }

      .voucher-card {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
        border-radius: 16px;
        padding: 18px;
        margin-top: 16px;
        text-align: left;
      }

      .btn-wa-action {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: #25D366;
        color: #ffffff !important;
        font-weight: 700;
        font-size: 14px;
        padding: 12px 20px;
        border-radius: 12px;
        text-decoration: none !important;
        box-shadow: 0 8px 25px rgba(37, 211, 102, 0.4);
        transition: all 0.25s ease;
        margin-top: 16px;
      }
      .btn-wa-action:hover {
        background: #20BA5A;
        transform: translateY(-2px);
      }
    </style>
  </head>
  <body>
    <div class="container" style="padding: 15px;">
      <div class="order-success-card text-center">

        @php
          $isPaid = $order->isPaid();
        @endphp

        <div id="status-icon-container">
          @if($isPaid)
            <div class="success-icon-box">
              <i class="fa fa-check"></i>
            </div>
            <h2 style="color: #fff; font-size: 22px; font-weight: 800; margin: 0 0 4px 0;">Pembayaran Berhasil!</h2>
            <p style="font-size: 13px; color: #10B981; font-weight: 700; margin-bottom: 0;">
              Pesanan Anda telah lunas &amp; siap digunakan.
            </p>
          @else
            <div class="pending-icon-box">
              <i class="fa fa-qrcode"></i>
            </div>
            <h2 style="color: #fff; font-size: 22px; font-weight: 800; margin: 0 0 4px 0;">Menunggu Pembayaran</h2>
            <p style="font-size: 13px; color: #94A3B8; margin-bottom: 0;">
              Scan QRIS di bawah ini untuk menyelesaikan pesanan Anda.
            </p>
          @endif
        </div>

        <div>
          <span class="order-num-badge">No. Order: {{ $order->order_number }}</span>
        </div>

        <!-- Customer & Shipping Details -->
        <div style="text-align: left; background: #070B11; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; border: 1px solid rgba(255,255,255,0.06); font-size: 12px;">
          <div class="row">
            <div class="col-sm-6" style="margin-bottom: 6px;">
              <span style="font-size: 10px; color: #94A3B8; text-transform: uppercase; font-weight: 700;">Nama Penerima:</span>
              <div style="font-weight: 700; color: #fff;">{{ $order->customer_name }}</div>
              <div style="color: #00E5FF; margin-top: 1px;"><i class="fa fa-whatsapp"></i> {{ $order->customer_phone }}</div>
            </div>
            <div class="col-sm-6">
              <span style="font-size: 10px; color: #94A3B8; text-transform: uppercase; font-weight: 700;">Pengiriman / Layanan:</span>
              <div style="color: #CBD5E1;">{{ $order->clean_shipping_address }}</div>
            </div>
          </div>
        </div>

        <!-- Items Table -->
        <table class="order-summary-table">
          <thead>
            <tr>
              <th style="text-align: left;">Produk</th>
              <th style="text-align: center;">Qty</th>
              <th style="text-align: right;">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            @foreach($order->items as $item)
              <tr>
                <td style="text-align: left;">
                  <strong style="color: #fff;">{{ $item->product_name }}</strong>
                </td>
                <td style="text-align: center;">{{ $item->quantity }}</td>
                <td style="text-align: right; font-weight: 700; color: #00E5FF;">{{ $item->formatted_subtotal }}</td>
              </tr>
            @endforeach
            <tr style="border-top: 2px solid rgba(255,255,255,0.1);">
              <td colspan="2" style="text-align: right; font-weight: 800; font-size: 13px; color: #fff; padding-top: 10px;">Total Pembayaran:</td>
              <td style="text-align: right; font-weight: 800; font-size: 16px; color: #00E5FF; padding-top: 10px;">{{ $order->formatted_total }}</td>
            </tr>
          </tbody>
        </table>

        <!-- JIKA SUDAH LUNAS & MEMILIKI VOUCHER -->
        <div id="voucher-box" style="{{ $isPaid && !empty($order->voucher_username) ? 'display: block;' : 'display: none;' }}">
          <div class="voucher-card">
            <div style="font-size: 13px; font-weight: 800; color: #10B981; margin-bottom: 8px; text-transform: uppercase;">
              <i class="fa fa-wifi"></i> Akun / Kode Voucher WiFi Anda:
            </div>
            <div style="background: #070B11; border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 10px; padding: 12px; margin-bottom: 8px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                <span style="font-size: 11px; color: #94A3B8;">Username:</span>
                <span style="font-family: monospace; font-size: 15px; font-weight: 800; color: #fff;" id="v-user">{{ $order->voucher_username }}</span>
                <button type="button" class="btn-copy" onclick="copyText(document.getElementById('v-user').innerText)">Salin</button>
              </div>
              @if(!empty($order->voucher_password))
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="font-size: 11px; color: #94A3B8;">Password:</span>
                  <span style="font-family: monospace; font-size: 15px; font-weight: 800; color: #00E5FF;" id="v-pass">{{ $order->voucher_password }}</span>
                  <button type="button" class="btn-copy" onclick="copyText(document.getElementById('v-pass').innerText)">Salin</button>
                </div>
              @endif
            </div>
            <p style="font-size: 11px; color: #94A3B8; margin-bottom: 0;">
              Hubungkan perangkat Anda ke jaringan WiFi, lalu masukkan Username dan Password di atas pada halaman login hotspot.
            </p>
          </div>
        </div>

        <!-- JIKA BELUM LUNAS: FINTECH DYNAMIC QRIS BOX -->
        <div id="payment-box" style="{{ $isPaid ? 'display: none;' : 'display: block;' }}">
          @if(!empty($qris['image']))
            <div class="qris-fintech-box">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <div style="text-align: left;">
                  <div style="font-size: 12px; font-weight: 800; color: #fff;">{{ $qris['merchant_name'] ?? 'NODERA' }}</div>
                  <div style="font-size: 10px; color: #94A3B8;">NMID: {{ $qris['nmid'] ?? 'ID1024366211885' }}</div>
                </div>
                <div style="text-align: right;">
                  <div style="font-size: 10px; font-weight: 700; color: #00E5FF; text-transform: uppercase;">QRIS Dinamis (0% Fee)</div>
                  <div style="font-size: 11px; color: #F59E0B; font-weight: 700;" id="timer-display">15:00</div>
                </div>
              </div>

              <!-- Barcode SVG Frame -->
              <div style="background: #ffffff; padding: 12px; border-radius: 16px; display: inline-block; margin: 0 auto 12px auto; box-shadow: 0 8px 30px rgba(0, 194, 255, 0.25);">
                {!! $qris['image'] !!}
              </div>

              <!-- Amount & Copy Box -->
              <div style="background: #070B11; border: 1px solid rgba(0, 194, 255, 0.25); border-radius: 12px; padding: 10px 14px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                <div style="text-align: left;">
                  <div style="font-size: 10px; color: #94A3B8; text-transform: uppercase;">Total Pembayaran Pas:</div>
                  <div style="font-family: monospace; font-size: 18px; font-weight: 800; color: #00E5FF;">
                    {{ $order->formatted_total }}
                  </div>
                </div>
                <button type="button" class="btn-copy" onclick="copyText('{{ (int) round($order->total_amount) }}')">
                  <i class="fa fa-copy"></i> Salin Nominal
                </button>
              </div>

              <div style="font-size: 11px; color: #94A3B8; line-height: 1.5;">
                Buka BCA, Mandiri, BRI, BNI, GoPay, OVO, DANA atau ShopeePay.<br>
                Scan barcode di atas. Pembayaran terverifikasi otomatis dalam beberapa detik!
              </div>

              <div style="margin-top: 12px;">
                <button type="button" class="btn-copy" style="padding: 6px 16px; font-size: 11px;" onclick="checkOrderStatusManual()">
                  <i class="fa fa-refresh"></i> Cek Status Pembayaran
                </button>
              </div>
            </div>
          @endif

          <!-- Backup Bank Accounts -->
          @if(!empty($bankAccounts) && count($bankAccounts) > 0)
            <div style="background: #070B11; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 14px; margin-top: 16px;">
              <div style="font-size: 11px; font-weight: 700; color: #94A3B8; margin-bottom: 8px; text-transform: uppercase;">
                Atau Transfer Manual Rekening:
              </div>
              @foreach($bankAccounts as $bank)
                <div class="bank-box">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="bank-box-title">{{ $bank->bank_name }}</div>
                    <button type="button" class="btn-copy" onclick="copyText('{{ $bank->account_number }}')">Salin</button>
                  </div>
                  <div class="bank-box-acc">{{ $bank->account_number }}</div>
                  <div style="font-size: 10px; color: #94A3B8;">a.n {{ $bank->account_name }}</div>
                </div>
              @endforeach
            </div>
          @endif
        </div>

        <!-- WhatsApp Action Button -->
        <a href="{{ $waUrl }}" target="_blank" class="btn-wa-action">
          <i class="fa fa-whatsapp" style="font-size: 20px;"></i>
          Hubungi Admin via WhatsApp
        </a>

        <div style="margin-top: 22px; display: flex; gap: 10px; justify-content: center;">
          <a href="/shop" class="btn btn-border-w btn-round btn-xs" style="padding: 6px 14px;">
            <i class="fa fa-shopping-bag"></i> Toko / Produk Lain
          </a>
          <a href="/" class="btn btn-border-w btn-round btn-xs" style="padding: 6px 14px;">
            <i class="fa fa-home"></i> Beranda
          </a>
        </div>
      </div>
    </div>

    <script>
      function copyText(text) {
        navigator.clipboard.writeText(text).then(() => {
          alert('Tersalin: ' + text);
        });
      }

      // Real-time Countdown Timer
      let secondsLeft = {{ (int) ($secondsLeft ?? 900) }};
      const timerEl = document.getElementById('timer-display');
      if (timerEl) {
        const timerInterval = setInterval(() => {
          if (secondsLeft <= 0) {
            clearInterval(timerInterval);
            timerEl.innerText = 'Kedaluwarsa';
            timerEl.style.color = '#EF4444';
            return;
          }
          secondsLeft--;
          const m = Math.floor(secondsLeft / 60);
          const s = secondsLeft % 60;
          timerEl.innerText = `${m}:${s < 10 ? '0' : ''}${s}`;
        }, 1000);
      }

      // Real-time Polling for payment auto-settlement
      const orderNumber = '{{ $order->order_number }}';
      const pollUrl = '/shop/order/' + orderNumber + '/status';
      let isSettled = {{ $isPaid ? 'true' : 'false' }};

      async function checkOrderStatusManual() {
        try {
          const res = await fetch(pollUrl, { headers: { 'Accept': 'application/json' } });
          const data = await res.json();
          if (data.is_paid) {
            handlePaidSuccess(data);
          } else {
            alert('Pembayaran belum diterima. Silakan selesaikan scan barcode QRIS di aplikasi m-Banking Anda.');
          }
        } catch (e) {
          console.error(e);
        }
      }

      function handlePaidSuccess(data) {
        if (isSettled) return;
        isSettled = true;

        document.getElementById('status-icon-container').innerHTML = `
          <div class="success-icon-box">
            <i class="fa fa-check"></i>
          </div>
          <h2 style="color: #fff; font-size: 22px; font-weight: 800; margin: 0 0 4px 0;">Pembayaran Berhasil!</h2>
          <p style="font-size: 13px; color: #10B981; font-weight: 700; margin-bottom: 0;">
            Pesanan Anda telah diverifikasi secara otomatis.
          </p>
        `;

        document.getElementById('payment-box').style.display = 'none';

        if (data.voucher && data.voucher.username) {
          document.getElementById('v-user').innerText = data.voucher.username;
          if (data.voucher.password) {
            document.getElementById('v-pass').innerText = data.voucher.password;
          }
          document.getElementById('voucher-box').style.display = 'block';
        }
      }

      if (!isSettled) {
        const pollInterval = setInterval(async () => {
          if (isSettled) {
            clearInterval(pollInterval);
            return;
          }
          try {
            const res = await fetch(pollUrl, { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
              const data = await res.json();
              if (data.is_paid) {
                handlePaidSuccess(data);
                clearInterval(pollInterval);
              }
            }
          } catch (err) {
            // Ignore network dropouts
          }
        }, 2500);
      }
    </script>
  </body>
</html>
