<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AddonController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AgentPortalController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\ClientLogController;
use App\Http\Controllers\CollectorAuthController;
use App\Http\Controllers\CollectorController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\MikrotikToolsController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\OltController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ArpController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PppoeController;
use App\Http\Controllers\QRISController;
use App\Http\Controllers\RadiusController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\TenantShopController;
use App\Http\Controllers\TechnicianAuthController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\TopBandwidthController;
use App\Http\Controllers\WebhookController;
use App\Models\Addon;
use App\Models\TenantAddon;
use Inertia\Inertia;

// ==================== PORTAL CUSTOMER ====================
Route::prefix('customer')->name('customer.')->group(function () {
    Route::get('/', [PortalController::class, 'index']);
    Route::get('login', [CustomerAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [CustomerAuthController::class, 'authenticate'])->middleware('throttle:10,1');
    Route::match(['get', 'post'], 'logout', [CustomerAuthController::class, 'logout'])->name('logout');
    Route::get('profile', [PortalController::class, 'profile'])->name('profile');
    Route::post('profile', [PortalController::class, 'updateProfile'])->name('profile.update');
    Route::get('profil', [PortalController::class, 'profile'])->name('profil');
    Route::post('pin', [PortalController::class, 'updatePin'])->name('pin');
    Route::match(['get', 'post'], 'laporan', [PortalController::class, 'laporan']);
    Route::get('wifi', [PortalController::class, 'editWifi']);
    Route::post('wifi', [PortalController::class, 'updateWifi'])->middleware('throttle:10,1');
    Route::post('wifi/update', [PortalController::class, 'updateWifi'])->middleware('throttle:10,1');
    Route::post('wifi/ssid', [PortalController::class, 'updateSsid']);
    Route::post('wifi/password', [PortalController::class, 'updatePassword'])->middleware('throttle:10,1');
    Route::post('wifi/portal-password', [PortalController::class, 'changePortalPassword'])->middleware('throttle:10,1');
    Route::post('reboot-ont', [PortalController::class, 'rebootOnt'])->middleware('throttle:5,1');
    Route::get('usage', [PortalController::class, 'usage']);
    Route::get('realtime-traffic', [PortalController::class, 'realtimeTraffic']);
    Route::match(['get', 'post'], 'speedtest/ping', [PortalController::class, 'speedtestPing']);
    Route::match(['get', 'post'], 'speedtest/download', [PortalController::class, 'speedtestDownload']);
    Route::match(['get', 'post'], 'speedtest/upload', [PortalController::class, 'speedtestUpload']);
    Route::get('invoices', [PortalController::class, 'invoices']);
    Route::get('payment/{invoiceId}', [PortalController::class, 'payment']);
    Route::post('payment/{invoiceId}/cancel', [PortalController::class, 'cancelActivePayment'])->middleware('throttle:60,1');
    Route::post('paymentManual', [PortalController::class, 'paymentManual'])->middleware('throttle:60,1');
    Route::post('processPayment', [PortalController::class, 'processPayment'])->middleware('throttle:60,1');
    Route::post('api/save-fcm-token', [PortalController::class, 'saveFcmToken']);
    Route::get('tos', [PortalController::class, 'tos']);
});

Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'index']);
    Route::get('login', [CustomerAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [CustomerAuthController::class, 'authenticate'])->middleware('throttle:10,1');
    Route::match(['get', 'post'], 'logout', [CustomerAuthController::class, 'logout'])->name('logout');
    Route::get('profile', [PortalController::class, 'profile'])->name('profile');
    Route::post('profile', [PortalController::class, 'updateProfile'])->name('profile.update');
    Route::get('profil', [PortalController::class, 'profile'])->name('profil');
    Route::post('pin', [PortalController::class, 'updatePin'])->name('pin');
    Route::match(['get', 'post'], 'laporan', [PortalController::class, 'laporan']);
    Route::get('wifi', [PortalController::class, 'editWifi']);
    Route::post('wifi', [PortalController::class, 'updateWifi'])->middleware('throttle:10,1');
    Route::post('wifi/update', [PortalController::class, 'updateWifi'])->middleware('throttle:10,1');
    Route::post('wifi/ssid', [PortalController::class, 'updateSsid']);
    Route::post('wifi/password', [PortalController::class, 'updatePassword'])->middleware('throttle:10,1');
    Route::post('wifi/portal-password', [PortalController::class, 'changePortalPassword'])->middleware('throttle:10,1');
    Route::post('reboot-ont', [PortalController::class, 'rebootOnt'])->middleware('throttle:5,1');
    Route::get('usage', [PortalController::class, 'usage']);
    Route::get('realtime-traffic', [PortalController::class, 'realtimeTraffic']);
    Route::match(['get', 'post'], 'speedtest/ping', [PortalController::class, 'speedtestPing']);
    Route::match(['get', 'post'], 'speedtest/download', [PortalController::class, 'speedtestDownload']);
    Route::match(['get', 'post'], 'speedtest/upload', [PortalController::class, 'speedtestUpload']);
    Route::get('invoices', [PortalController::class, 'invoices']);
    Route::get('payment/{invoiceId}', [PortalController::class, 'payment']);
    Route::post('payment/{invoiceId}/cancel', [PortalController::class, 'cancelActivePayment'])->middleware('throttle:60,1');
    Route::post('paymentManual', [PortalController::class, 'paymentManual'])->middleware('throttle:60,1');
    Route::post('processPayment', [PortalController::class, 'processPayment'])->middleware('throttle:60,1');
    Route::post('api/save-fcm-token', [PortalController::class, 'saveFcmToken']);
    Route::get('tos', [PortalController::class, 'tos']);
});

// ==================== PORTAL MOBILE ====================
Route::prefix('mobile')->name('mobile.')->group(function () {
    Route::get('login/{slug}', [CustomerAuthController::class, 'showLoginForm']);
    Route::post('login/{slug}', [CustomerAuthController::class, 'authenticate'])->middleware('throttle:10,1');
});

// ==================== PELANGGAN LOGIN (ALIAS) ====================
Route::get('pelanggan/login', [CustomerAuthController::class, 'showLoginForm']);
Route::post('pelanggan/login', [CustomerAuthController::class, 'authenticate'])->middleware('throttle:10,1');
Route::match(['get', 'post'], 'pelanggan/logout', [CustomerAuthController::class, 'logout']);



// ==================== AUTH ====================
Route::get('auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('login', [\App\Http\Controllers\Auth\LoginController::class, 'showGlobalLogin'])->name('login');
Route::post('login', [\App\Http\Controllers\Auth\LoginController::class, 'authenticate'])->middleware('throttle:10,1');
Route::post('auth/desktop-license', [\App\Http\Controllers\Auth\LoginController::class, 'saveDesktopLicense'])->middleware('throttle:20,1');
Route::match(['get', 'post'], 'logout', [\App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

// ==================== COLLECTOR PORTAL ====================
Route::prefix('collector')->name('collector.')->group(function () {
    Route::get('login', [CollectorAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [CollectorAuthController::class, 'authenticate'])->middleware('throttle:30,1');
    Route::match(['get', 'post'], 'logout', [CollectorAuthController::class, 'logout'])->name('logout');

    Route::middleware('auth.collector')->group(function () {
        Route::get('dashboard', [CollectorAuthController::class, 'dashboard'])->name('dashboard');
        Route::get('invoices', [CollectorAuthController::class, 'invoices'])->name('invoices');
        Route::get('tagihan', [CollectorAuthController::class, 'invoices']);
        Route::get('earnings', [CollectorAuthController::class, 'earnings'])->name('earnings');
        Route::redirect('checkin', '/collector/dashboard');
        Route::get('top-bandwidth', [CollectorAuthController::class, 'topBandwidth'])->name('top-bandwidth');
        Route::get('top-bandwidth/data', [TopBandwidthController::class, 'data']);
        Route::get('pppoe', [CollectorAuthController::class, 'pppoe'])->name('pppoe');
        Route::get('map', [CollectorAuthController::class, 'map'])->name('map');
        Route::get('arp', [ArpController::class, 'index'])->name('arp');
        Route::get('arp-binding', fn () => redirect('/collector/arp'));
        Route::post('arp/store', [ArpController::class, 'store'])->name('arp.store');
        Route::post('arp/add-static', [ArpController::class, 'store']);
        Route::post('arp/make-static', [ArpController::class, 'makeStatic'])->name('arp.make-static');
        Route::post('arp/toggle', [ArpController::class, 'toggle'])->name('arp.toggle');
        Route::post('arp/toggle-disabled', [ArpController::class, 'toggle']);
        Route::post('arp/delete', [ArpController::class, 'delete'])->name('arp.delete');
        Route::match(['get', 'post'], 'arp/ping', [ArpController::class, 'ping'])->name('arp.ping');
        Route::match(['get', 'post'], 'arp/ping-test', [ArpController::class, 'ping']);
        Route::post('pppoe/kick', [PppoeController::class, 'kick'])->name('pppoe.kick');
        Route::post('pppoe/delete-secret', [PppoeController::class, 'deleteSecret'])->name('pppoe.delete-secret');
        Route::post('check-in', [CollectorAuthController::class, 'checkIn']);
        Route::post('check-out', [CollectorAuthController::class, 'checkOut']);
        Route::post('bayar/{customer}', [CollectorAuthController::class, 'markPaid']);
        Route::post('bayar-batch', [CollectorAuthController::class, 'markPaidBatch']);
        Route::post('invoices/{id}/send-wa', [CollectorAuthController::class, 'sendWaReminder']);
        Route::get('profile', [CollectorAuthController::class, 'profile'])->name('profile');
        Route::post('profile', [CollectorAuthController::class, 'updateProfile'])->name('profile.update');
        Route::get('customers', [TechnicianAuthController::class, 'customers'])->name('customers');
        Route::get('pool', [TechnicianAuthController::class, 'pool'])->name('pool');
        Route::get('history', [TechnicianAuthController::class, 'history'])->name('history');
        Route::post('resolve/{id}', [TechnicianAuthController::class, 'resolveTicket'])->name('resolve');
        Route::post('take/{id}', [TechnicianAuthController::class, 'takeTicket'])->name('take');
        Route::post('api/save-fcm-token', [CollectorAuthController::class, 'saveFcmToken']);
    });
});

// Kolektor (Alias)
Route::prefix('kolektor')->name('kolektor.')->group(function () {
    Route::get('login', [CollectorAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [CollectorAuthController::class, 'authenticate'])->middleware('throttle:30,1');
    Route::match(['get', 'post'], 'logout', [CollectorAuthController::class, 'logout'])->name('logout');
    Route::middleware('auth.collector')->group(function () {
        Route::get('dashboard', [CollectorAuthController::class, 'dashboard'])->name('dashboard');
        Route::get('invoices', [CollectorAuthController::class, 'invoices'])->name('invoices');
        Route::get('tagihan', [CollectorAuthController::class, 'invoices']);
        Route::get('earnings', [CollectorAuthController::class, 'earnings'])->name('earnings');
        Route::redirect('checkin', '/collector/dashboard');
        Route::get('top-bandwidth', [CollectorAuthController::class, 'topBandwidth'])->name('top-bandwidth');
        Route::get('top-bandwidth/data', [TopBandwidthController::class, 'data']);
        Route::get('pppoe', [CollectorAuthController::class, 'pppoe'])->name('pppoe');
        Route::get('map', [CollectorAuthController::class, 'map'])->name('map');
        Route::get('arp', [ArpController::class, 'index'])->name('arp');
        Route::get('arp-binding', fn () => redirect('/kolektor/arp'));
        Route::post('arp/store', [ArpController::class, 'store'])->name('arp.store');
        Route::post('arp/add-static', [ArpController::class, 'store']);
        Route::post('arp/make-static', [ArpController::class, 'makeStatic'])->name('arp.make-static');
        Route::post('arp/toggle', [ArpController::class, 'toggle'])->name('arp.toggle');
        Route::post('arp/toggle-disabled', [ArpController::class, 'toggle']);
        Route::post('arp/delete', [ArpController::class, 'delete'])->name('arp.delete');
        Route::match(['get', 'post'], 'arp/ping', [ArpController::class, 'ping'])->name('arp.ping');
        Route::match(['get', 'post'], 'arp/ping-test', [ArpController::class, 'ping']);
        Route::post('pppoe/kick', [PppoeController::class, 'kick'])->name('pppoe.kick');
        Route::post('pppoe/delete-secret', [PppoeController::class, 'deleteSecret'])->name('pppoe.delete-secret');
        Route::post('check-in', [CollectorAuthController::class, 'checkIn']);
        Route::post('check-out', [CollectorAuthController::class, 'checkOut']);
        Route::post('bayar/{customer}', [CollectorAuthController::class, 'markPaid']);
        Route::post('bayar-batch', [CollectorAuthController::class, 'markPaidBatch']);
        Route::post('invoices/{id}/send-wa', [CollectorAuthController::class, 'sendWaReminder']);
        Route::get('profile', [CollectorAuthController::class, 'profile'])->name('profile');
        Route::post('profile', [CollectorAuthController::class, 'updateProfile'])->name('profile.update');
        Route::get('customers', [TechnicianAuthController::class, 'customers'])->name('customers');
        Route::get('pool', [TechnicianAuthController::class, 'pool'])->name('pool');
        Route::get('history', [TechnicianAuthController::class, 'history'])->name('history');
        Route::post('resolve/{id}', [TechnicianAuthController::class, 'resolveTicket'])->name('resolve');
        Route::get('invoices/print/{id}', [BillingController::class, 'printInvoice']);
        Route::get('print/{id}', [BillingController::class, 'printInvoice']);
        Route::post('api/save-fcm-token', [CollectorAuthController::class, 'saveFcmToken']);
    });
});

// ==================== TECHNICIAN PORTAL ====================
Route::prefix('technician')->name('technician.')->group(function () {
    Route::get('login', [TechnicianAuthController::class, 'showLoginForm']);
    Route::post('login', [TechnicianAuthController::class, 'authenticate'])->middleware('throttle:30,1');
    Route::match(['get', 'post'], 'logout', [TechnicianAuthController::class, 'logout']);

    Route::middleware('auth.technician')->group(function () {
        Route::get('dashboard', [TechnicianAuthController::class, 'dashboard']);
        Route::get('earnings', [TechnicianAuthController::class, 'earnings']);
        Route::post('check-in', [TechnicianAuthController::class, 'checkIn']);
        Route::post('check-out', [TechnicianAuthController::class, 'checkOut']);
        Route::post('resolve/{id}', [TechnicianAuthController::class, 'resolveTicket']);
        Route::post('take/{id}', [TechnicianAuthController::class, 'takeTicket']);
        Route::get('customers', [TechnicianAuthController::class, 'customers'])->name('customers');
        Route::get('create-customer', [TechnicianAuthController::class, 'createCustomer']);
        Route::post('create-customer/store', [TechnicianAuthController::class, 'storeCustomer']);
        Route::get('history', [TechnicianAuthController::class, 'history']);
        Route::get('pool', [TechnicianAuthController::class, 'pool']);
        Route::get('map', [TechnicianAuthController::class, 'map']);
        Route::get('pppoe', [TechnicianAuthController::class, 'pppoe'])->name('pppoe');
        Route::post('pppoe/kick', [PppoeController::class, 'kick'])->name('pppoe.kick');
        Route::post('pppoe/delete-secret', [PppoeController::class, 'deleteSecret'])->name('pppoe.delete-secret');
        Route::get('profile', [TechnicianAuthController::class, 'profile'])->name('profile');
        Route::post('profile', [TechnicianAuthController::class, 'updateProfile'])->name('profile.update');
        Route::get('invoices/print/{id}', [BillingController::class, 'printInvoice']);
        Route::get('print/{id}', [BillingController::class, 'printInvoice']);
        Route::get('api/onu-signal/{ticketId}', [TechnicianAuthController::class, 'onuSignal']);
        Route::post('api/onu-reboot/{ticketId}', [TechnicianAuthController::class, 'rebootOnu']);
        Route::post('api/save-fcm-token', [TechnicianAuthController::class, 'saveFcmToken']);
    });
});

// Teknisi (Alias)
Route::prefix('teknisi')->name('teknisi.')->group(function () {
    Route::get('login', [TechnicianAuthController::class, 'showLoginForm']);
    Route::post('login', [TechnicianAuthController::class, 'authenticate'])->middleware('throttle:30,1');
    Route::match(['get', 'post'], 'logout', [TechnicianAuthController::class, 'logout']);

    Route::middleware('auth.technician')->group(function () {
        Route::get('dashboard', [TechnicianAuthController::class, 'dashboard']);
        Route::get('earnings', [TechnicianAuthController::class, 'earnings']);
        Route::post('check-in', [TechnicianAuthController::class, 'checkIn']);
        Route::post('check-out', [TechnicianAuthController::class, 'checkOut']);
        Route::post('resolve/{id}', [TechnicianAuthController::class, 'resolveTicket']);
        Route::post('take/{id}', [TechnicianAuthController::class, 'takeTicket']);
        Route::get('customers', [TechnicianAuthController::class, 'customers'])->name('customers');
        Route::get('create-customer', [TechnicianAuthController::class, 'createCustomer']);
        Route::post('create-customer/store', [TechnicianAuthController::class, 'storeCustomer']);
        Route::get('history', [TechnicianAuthController::class, 'history']);
        Route::get('pool', [TechnicianAuthController::class, 'pool']);
        Route::get('map', [TechnicianAuthController::class, 'map']);
        Route::get('pppoe', [TechnicianAuthController::class, 'pppoe'])->name('pppoe');
        Route::post('pppoe/kick', [PppoeController::class, 'kick'])->name('pppoe.kick');
        Route::post('pppoe/delete-secret', [PppoeController::class, 'deleteSecret'])->name('pppoe.delete-secret');
        Route::get('arp', [TechnicianAuthController::class, 'arp'])->name('arp');
        Route::get('arp-binding', fn () => redirect('/teknisi/arp'));
        Route::post('arp/store', [ArpController::class, 'store'])->name('arp.store');
        Route::post('arp/add-static', [ArpController::class, 'store']);
        Route::post('arp/make-static', [ArpController::class, 'makeStatic'])->name('arp.make-static');
        Route::post('arp/toggle', [ArpController::class, 'toggle'])->name('arp.toggle');
        Route::post('arp/toggle-disabled', [ArpController::class, 'toggle']);
        Route::post('arp/delete', [ArpController::class, 'delete'])->name('arp.delete');
        Route::match(['get', 'post'], 'arp/ping', [ArpController::class, 'ping'])->name('arp.ping');
        Route::match(['get', 'post'], 'arp/ping-test', [ArpController::class, 'ping']);
        Route::get('profile', [TechnicianAuthController::class, 'profile'])->name('profile');
        Route::post('profile', [TechnicianAuthController::class, 'updateProfile'])->name('profile.update');
        Route::get('invoices/print/{id}', [BillingController::class, 'printInvoice']);
        Route::get('print/{id}', [BillingController::class, 'printInvoice']);
        Route::get('api/onu-signal/{ticketId}', [TechnicianAuthController::class, 'onuSignal']);
        Route::post('api/onu-reboot/{ticketId}', [TechnicianAuthController::class, 'rebootOnu']);
        Route::post('api/save-fcm-token', [TechnicianAuthController::class, 'saveFcmToken']);
    });
});

Route::redirect('collector', '/kolektor/dashboard');
Route::redirect('collector/dashboard', '/kolektor/dashboard');
Route::redirect('technician', '/teknisi/dashboard');
Route::redirect('technician/dashboard', '/teknisi/dashboard');
Route::redirect('technician/arp', '/teknisi/arp');
Route::redirect('olt', '/admin/olt');
Route::redirect('onus', '/admin/olt');

// ==================== VPN REMOTE PORTAL ====================

// ==================== PORTAL AGEN ====================
Route::prefix('agent')->name('agent.')->group(function () {
    Route::get('login', [AgentPortalController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AgentPortalController::class, 'login'])->middleware('throttle:10,1');
    Route::match(['get', 'post'], 'logout', [AgentPortalController::class, 'logout'])->name('logout');
    Route::get('dashboard', [AgentPortalController::class, 'dashboard'])->name('dashboard');
    Route::post('pay-invoice', [AgentPortalController::class, 'payInvoice']);
    Route::post('sell-voucher', [AgentPortalController::class, 'sellVoucher']);
    Route::get('transactions', [AgentPortalController::class, 'transactions'])->name('transactions');
});

// ==================== CASHIER ====================
Route::prefix('cashier')->group(function () {
    Route::get('login', [CashierController::class, 'showLoginForm']);
    Route::post('login', [CashierController::class, 'login'])->middleware('throttle:10,1');
    Route::get('dashboard', [CashierController::class, 'dashboard']);
    Route::post('pay', [CashierController::class, 'payInvoice']);
    Route::post('close', [CashierController::class, 'closeSession']);
    Route::match(['get', 'post'], 'logout', [CashierController::class, 'logout']);
});

Route::redirect('vpn/dashboard', '/dashboard');
Route::redirect('vpn/login', '/login');

