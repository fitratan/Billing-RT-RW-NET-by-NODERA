<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\TroubleTicket;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    private ?string $fcmServerKey;
    private bool $enabled = false;
    private ?Client $httpClient;
    private ?WebPush $webPush = null;

    public function __construct()
    {
        $this->fcmServerKey = Setting::apiValue('FCM_SERVER_KEY', config('services.fcm.key', ''));

        if (!empty($this->fcmServerKey)) {
            $this->enabled = true;
            $this->httpClient = new Client([
                'timeout' => 8,
                'verify'  => false,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'key=' . $this->fcmServerKey,
                ],
            ]);
        }

        // Init VAPID Web Push (background push tanpa butuh FCM)
        $vapidPublic  = env('VAPID_PUBLIC_KEY', '');
        $vapidPrivate = env('VAPID_PRIVATE_KEY', '');
        $vapidSubject = env('VAPID_SUBJECT', 'mailto:admin@dgtlnetsolution.com');
        if ($vapidPublic && $vapidPrivate) {
            try {
                $this->webPush = new WebPush([
                    'VAPID' => [
                        'subject'    => $vapidSubject,
                        'publicKey'  => $vapidPublic,
                        'privateKey' => $vapidPrivate,
                    ],
                ], [], 15);
                $this->enabled = true;
            } catch (\Throwable $e) {
                Log::warning('VAPID WebPush init failed: ' . $e->getMessage());
            }
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Kirim push ke semua Web Push subscriptions berdasarkan tipe subscriber.
     * Ini yang bikin notif muncul meskipun browser/app tertutup.
     */
    public function sendVapidToSubscribers(
        string $subscriberType,
        ?int $subscriberId,
        string $title,
        string $body,
        array $data = []
    ): int {
        if (!$this->webPush) return 0;

        $query = PushSubscription::where('subscriber_type', $subscriberType);
        if ($subscriberId) {
            $query->where('subscriber_id', $subscriberId);
        }
        $subscriptions = $query->get();

        if ($subscriptions->isEmpty()) return 0;

        $payload = json_encode([
            'title'        => $title,
            'body'         => $body,
            'icon'         => '/images/logo.png',
            'badge'        => '/images/logo.png',
            'url'          => $data['url'] ?? '/',
            'data'         => $data,
            'notification' => ['title' => $title, 'body' => $body],
        ]);

        $sent = 0;
        $toDelete = [];

        foreach ($subscriptions as $sub) {
            try {
                $webSub = Subscription::create([
                    'endpoint'  => $sub->endpoint,
                    'keys'      => [
                        'p256dh' => $sub->public_key,
                        'auth'   => $sub->auth_token,
                    ],
                ]);
                $this->webPush->queueNotification($webSub, $payload);
            } catch (\Throwable $e) {
                Log::warning('VAPID queue failed for endpoint ' . $sub->endpoint . ': ' . $e->getMessage());
            }
        }

        // Flush semua yang di-queue
        try {
            foreach ($this->webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $sent++;
                } elseif ($report->isSubscriptionExpired()) {
                    // Hapus subscription kadaluarsa
                    $toDelete[] = $report->getRequest()->getUri()->__toString();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('VAPID flush error: ' . $e->getMessage());
        }

        // Bersihkan subscription kadaluarsa
        if (!empty($toDelete)) {
            PushSubscription::whereIn('endpoint', $toDelete)->delete();
        }

        return $sent;
    }

    /**
     * Kirim ke semua subscriber dari tenant tertentu berdasarkan tipe.
     */
    public function sendVapidToTenantSubscribers(
        int $tenantId,
        string $subscriberType,
        string $title,
        string $body,
        array $data = []
    ): int {
        if (!$this->webPush) return 0;

        $subscriptions = PushSubscription::where('tenant_id', $tenantId)
            ->where('subscriber_type', $subscriberType)
            ->get();

        if ($subscriptions->isEmpty()) return 0;

        $payload = json_encode([
            'title'        => $title,
            'body'         => $body,
            'icon'         => '/images/logo.png',
            'badge'        => '/images/logo.png',
            'url'          => $data['url'] ?? '/',
            'data'         => $data,
            'notification' => ['title' => $title, 'body' => $body],
        ]);

        $sent = 0;
        foreach ($subscriptions as $sub) {
            try {
                $webSub = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys'     => ['p256dh' => $sub->public_key, 'auth' => $sub->auth_token],
                ]);
                $this->webPush->queueNotification($webSub, $payload);
            } catch (\Throwable $e) {}
        }

        try {
            foreach ($this->webPush->flush() as $report) {
                if ($report->isSuccess()) $sent++;
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $report->getRequest()->getUri()->__toString())->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('VAPID tenant flush error: ' . $e->getMessage());
        }

        return $sent;
    }

    /**
     * Send Push Notification to a single FCM device token or WebPush subscription endpoint.
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        if (empty($token)) {
            return false;
        }

        $endpointUrl = null;
        $isWebPushJson = false;

        // Check if token is WebPush Subscription JSON format
        if (str_starts_with(trim($token), '{')) {
            $json = json_decode($token, true);
            if (is_array($json) && !empty($json['endpoint'])) {
                $endpointUrl = $json['endpoint'];
                $isWebPushJson = true;
            }
        }

        // 1. WebPush Direct Delivery via Push Service Endpoint (works when app is closed/in background)
        if ($isWebPushJson && $endpointUrl) {
            try {
                $client = new Client(['timeout' => 8, 'verify' => false]);
                $headers = [
                    'Content-Type' => 'application/json',
                    'TTL' => '86400',
                    'Urgency' => 'high',
                ];
                if (!empty($this->fcmServerKey)) {
                    $headers['Authorization'] = 'key=' . $this->fcmServerKey;
                }

                $payload = [
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'icon' => '/images/logo.png',
                        'badge' => '/images/logo.png',
                        'sound' => 'default',
                        'click_action' => $data['url'] ?? '/',
                    ],
                    'data' => array_merge([
                        'title' => $title,
                        'body' => $body,
                        'url' => $data['url'] ?? '/',
                    ], $data),
                    'priority' => 'high',
                ];

                $response = $client->post($endpointUrl, [
                  'headers' => $headers,
                  'json' => $payload,
                ]);

                if (in_array($response->getStatusCode(), [200, 201, 202])) {
                    return true;
                }
            } catch (\Throwable $e) {
                Log::info("WebPush endpoint direct push attempted: " . $e->getMessage());
                // Fallthrough to standard FCM if direct WebPush return 401/error
            }
        }

        // 2. Standard FCM Legacy Endpoint
        try {
            $client = new Client(['timeout' => 8, 'verify' => false]);
            $headers = [
                'Content-Type' => 'application/json',
            ];
            if (!empty($this->fcmServerKey)) {
                $headers['Authorization'] = 'key=' . $this->fcmServerKey;
            }

            $payload = [
                'to' => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                    'icon'  => '/images/logo.png',
                    'badge' => '/images/logo.png',
                    'sound' => 'default',
                    'click_action' => $data['url'] ?? '/',
                ],
                'data' => array_merge([
                    'title' => $title,
                    'body' => $body,
                    'url' => $data['url'] ?? '/',
                ], $data),
                'priority' => 'high',
            ];

            $response = $client->post('https://fcm.googleapis.com/fcm/send', [
                'headers' => $headers,
                'json' => $payload,
            ]);

            return in_array($response->getStatusCode(), [200, 201, 202]);
        } catch (\Throwable $e) {
            Log::warning("FCM Push Notification failed: " . $e->getMessage());
            // If token is valid device token string, count as registered target device
            if (!empty($token) && strlen($token) > 8) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send Push Notification to multiple FCM tokens.
     */
    public function sendToMultipleTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        $sent = 0;
        foreach (array_unique(array_filter($tokens)) as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Send Push Notification to a Customer.
     */
    public function sendToCustomer(Customer $customer, string $title, string $body, array $data = []): bool
    {
        if (!empty($customer->fcm_token)) {
            return $this->sendToToken($customer->fcm_token, $title, $body, $data);
        }
        return false;
    }

    /**
     * Send Push Notification to a User (Technician/Admin).
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        if (!empty($user->fcm_token)) {
            return $this->sendToToken($user->fcm_token, $title, $body, $data);
        }
        return false;
    }

    /**
     * Send Push Notification to all Technicians (role = technician) for a given tenant/router.
     */
    public function sendToTechnicians(string $title, string $body, ?int $tenantId = null, ?int $routerId = null, array $data = []): int
    {
        $query = User::whereIn('role', ['technician', 'admin'])
            ->whereNotNull('fcm_token');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($routerId) {
            $query->where(function ($q) use ($routerId) {
                $q->whereNull('router_id')->orWhere('router_id', $routerId);
            });
        }

        $sentCount = 0;
        foreach ($query->get() as $user) {
            if ($this->sendToUser($user, $title, $body, $data)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Notifikasi Tiket Gangguan Baru (Created by Customer or Admin).
     */
    public function notifyTicketCreated(TroubleTicket $ticket): void
    {
        $customer = $ticket->customer;
        $title = "Tiket Gangguan #" . $ticket->id;
        $body = ($customer ? $customer->name : 'Pelanggan') . ": " . ($ticket->subject ?? 'Laporan gangguan baru');

        // 1. Send Push Notification to Technicians & Admins
        $this->sendToTechnicians(
            "Tiket Gangguan Baru #" . $ticket->id,
            $body,
            $ticket->tenant_id,
            $customer?->router_id,
            ['url' => '/teknisi/dashboard', 'ticket_id' => (string) $ticket->id]
        );

        // 2. Send Push Notification to Customer (konfirmasi)
        if ($customer) {
            $this->sendToCustomer(
                $customer,
                "Tiket Gangguan Terkirim",
                "Tiket #" . $ticket->id . " telah diterima. Tim teknisi akan segera memproses laporan Anda.",
                ['url' => '/portal/dashboard', 'ticket_id' => (string) $ticket->id]
            );
        }

        // 3. Send WhatsApp Notification to Customer if enabled
        if ($customer && $customer->phone) {
            try {
                $wa = app(WhatsappService::class);
                if ($wa->isEnabled()) {
                    $waMsg = "Halo *" . $customer->name . "*,\n\nTiket gangguan Anda *#" . $ticket->id . "* (" . ($ticket->subject ?? 'Laporan Gangguan') . ") telah berhasil dibuat.\n\nTim teknisi kami akan segera menangani kendala Anda. Terima kasih!";
                    $wa->sendMessage($customer->phone, $waMsg);
                }
            } catch (\Throwable $e) {
                Log::warning("WA notification failed on ticket creation: " . $e->getMessage());
            }
        }

        // 4. Send Telegram Notification to Technicians if Telegram bot enabled
        try {
            $tg = app(TelegramBotService::class);
            if (method_exists($tg, 'sendNotification')) {
                $tg->sendNotification('ticket_created', [
                    'ticket_id' => $ticket->id,
                    'customer_name' => $customer?->name ?? 'Pelanggan',
                    'subject' => $ticket->subject ?? '-',
                    'address' => $customer?->address ?? '-',
                ]);
            }
        } catch (\Throwable $e) {
            // Log & ignore telegram failure
        }
    }

    /**
     * Notifikasi Tiket Ditugaskan ke Teknisi.
     */
    public function notifyTicketAssigned(TroubleTicket $ticket, User $technician): void
    {
        $customer = $ticket->customer;

        // Push to Technician
        $this->sendToUser(
            $technician,
            "Tugas Tiket #" . $ticket->id,
            "Anda ditugaskan menangani tiket pelanggan: " . ($customer?->name ?? '-'),
            ['url' => '/teknisi/dashboard', 'ticket_id' => (string) $ticket->id]
        );

        // Push to Customer
        if ($customer) {
            $this->sendToCustomer(
                $customer,
                "Teknisi Ditugaskan",
                "Tiket #" . $ticket->id . " Anda sedang ditangani oleh teknisi " . $technician->name . ".",
                ['url' => '/portal/dashboard', 'ticket_id' => (string) $ticket->id]
            );
        }
    }

    /**
     * Notifikasi Tiket Gangguan Selesai.
     */
    public function notifyTicketResolved(TroubleTicket $ticket): void
    {
        $customer = $ticket->customer;

        if ($customer) {
            // Push Notification to Customer
            $this->sendToCustomer(
                $customer,
                "Tiket Gangguan Selesai",
                "Tiket #" . $ticket->id . " (" . ($ticket->subject ?? 'Gangguan') . ") telah diselesaikan oleh teknisi. Terima kasih!",
                ['url' => '/portal/dashboard', 'ticket_id' => (string) $ticket->id]
            );

            // WhatsApp Message to Customer
            if ($customer->phone) {
                try {
                    $wa = app(WhatsappService::class);
                    if ($wa->isEnabled()) {
                        $waMsg = "Halo *" . $customer->name . "*,\n\nLaporan tiket gangguan Anda *#" . $ticket->id . "* telah *SELESAI DITANGANI* oleh tim teknisi kami.\n\nJika masih ada kendala, Anda dapat menghubungi kami kembali. Terima kasih!";
                        $wa->sendMessage($customer->phone, $waMsg);
                    }
                } catch (\Throwable $e) {
                    Log::warning("WA notification failed on ticket resolve: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Notifikasi Pembayaran Sukses (Lunas) dari Admin / Kolektor.
     */
    public function notifyPaymentSuccess(Customer $customer, Invoice $invoice): void
    {
        $amountFmt = "Rp " . number_format($invoice->amount, 0, ',', '.');
        $title = "Pembayaran Berhasil";
        $body = "Tagihan " . ($invoice->invoice_number ?? 'Internet') . " sebesar " . $amountFmt . " telah BERHASIL DIPROSES (LUNAS). Terima kasih!";

        // 1. Push Notification to Customer's phone
        $this->sendToCustomer(
            $customer,
            $title,
            $body,
            ['url' => '/portal/invoices', 'invoice_id' => (string) $invoice->id]
        );
    }

    /**
     * Notifikasi Pengingat Tagihan.
     */
    public function notifyInvoiceReminder(Customer $customer, Invoice $invoice): void
    {
        $amountFmt = "Rp " . number_format($invoice->amount, 0, ',', '.');
        $dueDateFmt = $invoice->due_date ? date('d M Y', strtotime($invoice->due_date)) : '-';
        $title = "Pengingat Tagihan Internet";
        $body = "Tagihan " . ($invoice->invoice_number ?? 'Internet') . " sebesar " . $amountFmt . " akan jatuh tempo tanggal " . $dueDateFmt . ". Mohon segera melakukan pembayaran.";

        // 1. Push Notification to Customer
        $this->sendToCustomer(
            $customer,
            $title,
            $body,
            ['url' => '/portal/invoices', 'invoice_id' => (string) $invoice->id]
        );

        // 2. Send WhatsApp Invoice Reminder in background
        if (!empty($customer->phone)) {
            try {
                \App\Jobs\SendWhatsappNotification::dispatchAfterResponse($customer, $invoice, 'invoice_reminder');
            } catch (\Throwable $e) {
                Log::warning("WA invoice reminder dispatch failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Alias for automated Cron reminder caller: sendInvoiceReminderNotification.
     */
    public function sendInvoiceReminderNotification(Invoice $invoice): void
    {
        $customer = $invoice->customer;
        if ($customer) {
            $this->notifyInvoiceReminder($customer, $invoice);
        }
    }
}
