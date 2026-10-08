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

Route::middleware('auth.or.tech')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/api/stats', [DashboardController::class, 'apiStats']);

    // Topup aliases under auth.or.tech
    Route::get('/topup', [\App\Http\Controllers\Vpn\TopupController::class, 'index']);
    Route::get('/topup/create', [\App\Http\Controllers\Vpn\TopupController::class, 'create']);
    Route::post('/topup/store', [\App\Http\Controllers\Vpn\TopupController::class, 'store']);
    Route::post('/topup', [\App\Http\Controllers\Vpn\TopupController::class, 'store']);
    Route::get('/topup/confirm/{id}', [\App\Http\Controllers\Vpn\TopupController::class, 'confirm']);
    Route::post('/topup/confirm/{id}', [\App\Http\Controllers\Vpn\TopupController::class, 'submitProof']);
    Route::get('/topup/check-status/{id}', [\App\Http\Controllers\Vpn\TopupController::class, 'checkStatus']);
    Route::post('/topup/regenerate-qris/{id}', [\App\Http\Controllers\Vpn\TopupController::class, 'regenerateQris']);
    Route::post('/topup/cancel/{id}', [\App\Http\Controllers\Vpn\TopupController::class, 'cancel']);
    Route::get('/topup/history', [\App\Http\Controllers\Vpn\TopupController::class, 'history']);

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'dashboard']);
        Route::redirect('dashboard', '/dashboard');
        Route::get('semua-fitur', [AdminController::class, 'semuaFitur']);
        Route::get('panel', [AdminController::class, 'semuaFitur']);
        Route::redirect('settings', '/admin/my-settings');
        Route::redirect('setting', '/admin/my-settings');
        Route::get('my-settings', [SettingsController::class, 'index'])->middleware('throttle:60,1');
        Route::get('service-settings', [SettingsController::class, 'serviceSettings']);
        Route::post('my-settings/profile', [SettingsController::class, 'updateProfile'])->middleware('throttle:10,1');
        Route::post('my-settings/password', [SettingsController::class, 'changePassword'])->middleware('throttle:5,1');
        Route::post('api/save-fcm-token', [AdminController::class, 'saveFcmToken']);
        // Admin-only: settings that affect tenant
        Route::middleware('role:admin,superadmin')->group(function () {
            Route::post('my-settings/company', [SettingsController::class, 'updateCompanyProfile']);
            Route::post('my-settings/save', [SettingsController::class, 'save']);
            Route::post('my-settings/bank/store', [SettingsController::class, 'bankStore']);
            Route::post('my-settings/bank/add', [SettingsController::class, 'bankStore']);
            Route::get('my-settings/bank/edit/{id}', [SettingsController::class, 'bankEdit']);
            Route::get('my-settings/bank/{id}/edit', [SettingsController::class, 'bankEdit']);
            Route::post('my-settings/bank/update/{id}', [SettingsController::class, 'bankUpdate']);
            Route::post('my-settings/bank/delete/{id}', [SettingsController::class, 'bankDelete']);
            Route::post('my-settings/payment-methods', [SettingsController::class, 'savePaymentSettings']);
            Route::post('settings/payment-methods', [SettingsController::class, 'savePaymentSettings']);
        });
        Route::post('my-settings/setTelegramWebhook', [SettingsController::class, 'setTelegramWebhook'])->middleware('role:admin,superadmin');
        Route::post('my-settings/deleteTelegramWebhook', [SettingsController::class, 'deleteTelegramWebhook'])->middleware('role:admin,superadmin');
        Route::get('my-settings/check-update', [SettingsController::class, 'checkUpdate'])->middleware('role:admin,superadmin');
        Route::post('my-settings/system-update', [SettingsController::class, 'executeSystemUpdate'])->middleware('role:admin,superadmin');
        Route::post('my-settings/save-license', [SettingsController::class, 'saveLicense'])->middleware('role:admin,superadmin');
        Route::post('my-settings/upgrade-package', [SettingsController::class, 'upgradePackage'])->middleware('role:admin,superadmin');
        Route::post('my-settings/toggle-auto-renew', [SettingsController::class, 'toggleAutoRenew'])->middleware('role:admin,superadmin');

        // ── In-App System Update (Admin Standalone) ──
        Route::get('system-update', [SettingsController::class, 'systemUpdatePage'])->name('system.update')->middleware('role:admin,superadmin');
        Route::get('system-update/check', [SettingsController::class, 'checkUpdate'])->middleware('role:admin,superadmin');
        Route::post('system-update/execute', [SettingsController::class, 'executeSystemUpdate'])->middleware('role:admin,superadmin');
        Route::post('system-update/perform', [SettingsController::class, 'executeSystemUpdate'])->middleware('role:admin,superadmin');
        Route::post('system-update/license', [SettingsController::class, 'saveLicense'])->middleware('role:admin,superadmin');

        Route::redirect('packages', '/admin/billing/packages');
        Route::redirect('customers', '/admin/billing/customers');
        Route::redirect('invoices', '/admin/billing/invoices');

        // ── Tenant Shop Products & Orders & Landing Settings ──
        Route::prefix('shop')->name('shop.')->group(function () {
            Route::get('products', [\App\Http\Controllers\Admin\ShopProductController::class, 'index'])->name('products.index');
            Route::post('products', [\App\Http\Controllers\Admin\ShopProductController::class, 'store'])->name('products.store');
            Route::post('products/{id}/update', [\App\Http\Controllers\Admin\ShopProductController::class, 'update'])->name('products.update');
            Route::delete('products/{id}', [\App\Http\Controllers\Admin\ShopProductController::class, 'destroy'])->name('products.destroy');
            Route::post('products/{id}/toggle', [\App\Http\Controllers\Admin\ShopProductController::class, 'toggleActive'])->name('products.toggle');
            Route::post('categories', [\App\Http\Controllers\Admin\ShopProductController::class, 'storeCategory'])->name('categories.store');
            Route::post('categories/{id}/update', [\App\Http\Controllers\Admin\ShopProductController::class, 'updateCategory'])->name('categories.update');
            Route::delete('categories/{id}', [\App\Http\Controllers\Admin\ShopProductController::class, 'destroyCategory'])->name('categories.destroy');

            Route::get('orders', [\App\Http\Controllers\Admin\ShopOrderController::class, 'index'])->name('orders.index');
            Route::post('orders/{id}/approve', [\App\Http\Controllers\Admin\ShopOrderController::class, 'approve'])->name('orders.approve');
            Route::post('orders/{id}/reject', [\App\Http\Controllers\Admin\ShopOrderController::class, 'reject'])->name('orders.reject');
            Route::post('orders/{id}/update-status', [\App\Http\Controllers\Admin\ShopOrderController::class, 'updateStatus'])->name('orders.update-status');
            Route::post('orders/{id}/status', [\App\Http\Controllers\Admin\ShopOrderController::class, 'updateStatus']);
            Route::post('orders/{id}/delete', [\App\Http\Controllers\Admin\ShopOrderController::class, 'destroy'])->name('orders.delete');
            Route::delete('orders/{id}', [\App\Http\Controllers\Admin\ShopOrderController::class, 'destroy'])->name('orders.destroy');
        });

        Route::get('landing-settings', [\App\Http\Controllers\Admin\LandingSettingController::class, 'index'])->name('landing-settings.index');
        Route::post('landing-settings', [\App\Http\Controllers\Admin\LandingSettingController::class, 'update'])->name('landing-settings.update');
        Route::post('landing-settings/test-telegram', [\App\Http\Controllers\Admin\LandingSettingController::class, 'testTelegram'])->name('landing-settings.test-telegram');

        // ── Manajemen ONT & GenieACS Cloud ──
        Route::prefix('ont-devices')->name('ont-devices.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\OntDeviceController::class, 'index'])->name('index');
            Route::post('{id}/reboot', [\App\Http\Controllers\Admin\OntDeviceController::class, 'reboot'])->name('reboot');
            Route::post('{id}/wifi', [\App\Http\Controllers\Admin\OntDeviceController::class, 'updateWifi'])->name('wifi');
            Route::post('{id}/refresh', [\App\Http\Controllers\Admin\OntDeviceController::class, 'refreshMetrics'])->name('refresh');
            Route::post('{id}/assign', [\App\Http\Controllers\Admin\OntDeviceController::class, 'assignCustomer'])->name('assign');
            Route::post('sync', [\App\Http\Controllers\Admin\OntDeviceController::class, 'syncAcs'])->name('sync');
            Route::post('sync-acs', [\App\Http\Controllers\Admin\OntDeviceController::class, 'syncAcs'])->name('sync-acs');
            Route::post('settings', [\App\Http\Controllers\Admin\OntDeviceController::class, 'updateSettings'])->name('settings');
        });
        
        // ── LITCH AI Agent & Sub-Agent Mission Control ──










        Route::get('telegram', [\App\Http\Controllers\Admin\TelegramSettingController::class, 'index'])->name('telegram');
        Route::post('telegram/save', [\App\Http\Controllers\Admin\TelegramSettingController::class, 'update'])->middleware('role:admin,superadmin')->name('telegram.save');
        Route::post('telegram/test', [\App\Http\Controllers\Admin\TelegramSettingController::class, 'testTelegram'])->middleware('role:admin,superadmin')->name('telegram.test');
        Route::redirect('onus', '/admin/olt');
        Route::redirect('onu', '/admin/olt');
        Route::get('analytics', [AdminController::class, 'analytics']);
        Route::get('genieacs', [AdminController::class, 'genieacs']);
        Route::get('api/genieacs/device', [AdminController::class, 'genieacsDevice']);
        Route::post('api/genieacs/reboot', [AdminController::class, 'genieacsReboot'])->middleware('role:admin,superadmin');
        Route::post('api/genieacs/refresh', [AdminController::class, 'genieacsRefresh'])->middleware('role:admin,superadmin');
        Route::post('api/genieacs/factory-reset', [AdminController::class, 'genieacsFactoryReset'])->middleware('role:admin,superadmin');
        Route::post('api/genieacs/wifi', [AdminController::class, 'genieacsWifi'])->middleware('role:admin,superadmin');
        Route::post('api/genieacs/pppoe', [AdminController::class, 'genieacsPppoe'])->middleware('role:admin,superadmin');
        Route::post('api/genieacs/delete', [AdminController::class, 'genieacsDelete'])->middleware('role:admin,superadmin');


        // VPN Remote Admin Management
        Route::get('vpn', [\App\Http\Controllers\AdminVpnController::class, 'index']);
        Route::get('vpn/check-username', [\App\Http\Controllers\AdminVpnController::class, 'checkUsername']);
        Route::post('vpn/create', [\App\Http\Controllers\AdminVpnController::class, 'store'])->middleware('role:admin,superadmin');
        Route::post('vpn/delete/{id}', [\App\Http\Controllers\AdminVpnController::class, 'destroy'])->middleware('role:admin,superadmin');

        Route::post('dashboard-menus/save', [\App\Http\Controllers\DashboardController::class, 'saveMenus'])->middleware('role:admin,superadmin');
        Route::get('notifications', [AdminController::class, 'systemNotifications'])->name('notifications');
        Route::get('broadcast', fn () => redirect('/admin/whatsapp-templates'));
        Route::post('broadcast/send', [AdminController::class, 'broadcastSend'])->middleware('role:admin,superadmin');
        Route::post('broadcast/test-push', [AdminController::class, 'testPushNotification'])->middleware('role:admin,superadmin');
        Route::post('broadcast/test-wa', [AdminController::class, 'testWhatsappMessage'])->middleware('role:admin,superadmin');
        Route::post('broadcast/notif-settings', [AdminController::class, 'saveNotifSettings'])->middleware('role:admin,superadmin');
        Route::post('broadcast/wa-settings', [AdminController::class, 'saveWaSettings'])->middleware('role:admin,superadmin');
        Route::get('whatsapp-templates', [AdminController::class, 'whatsappTemplates'])->name('admin.whatsapp-templates');
        Route::post('whatsapp-templates/save', [AdminController::class, 'whatsappTemplateSave'])->middleware('role:admin,superadmin');
        Route::post('whatsapp-templates/toggle/{id}', [AdminController::class, 'whatsappTemplateToggle'])->middleware('role:admin,superadmin');
        Route::match(['POST', 'DELETE'], 'whatsapp-templates/delete/{id}', [AdminController::class, 'whatsappTemplateDelete'])->middleware('role:admin,superadmin');
        Route::post('whatsapp-templates/reset-defaults', [AdminController::class, 'whatsappTemplateResetDefaults'])->middleware('role:admin,superadmin');
        Route::post('whatsapp/test-and-save', [AdminController::class, 'testAndSaveWaSettings'])->middleware('role:admin,superadmin');
        Route::match(['GET', 'POST'], 'whatsapp/test-message', [AdminController::class, 'testWhatsappMessage'])->middleware('role:admin,superadmin');
        Route::match(['GET', 'POST'], 'whatsapp-templates/test-message', [AdminController::class, 'testWhatsappMessage'])->middleware('role:admin,superadmin');
        Route::match(['GET', 'POST'], 'whatsapp/device-status', [AdminController::class, 'checkWhatsappDeviceStatus'])->middleware('role:admin,superadmin');
        Route::match(['GET', 'POST'], 'whatsapp-templates/device-status', [AdminController::class, 'checkWhatsappDeviceStatus'])->middleware('role:admin,superadmin');
        Route::match(['GET', 'POST'], 'whatsapp/check-device', [AdminController::class, 'checkWhatsappDeviceStatus'])->middleware('role:admin,superadmin');
        Route::post('whatsapp/save-notif-settings', [AdminController::class, 'saveNotifSettings'])->middleware('role:admin,superadmin');
        Route::post('whatsapp/notif-settings', [AdminController::class, 'saveNotifSettings'])->middleware('role:admin,superadmin');

        // WhatsApp Gateway (Redirect to Unified WhatsApp Templates Page)
        Route::get('whatsapp-gateway', function () {
            return redirect('/admin/whatsapp-templates');
        })->name('admin.whatsapp-gateway');
        Route::post('genieacs/test-and-save', [AdminController::class, 'testAndSaveGenieacsSettings'])->middleware('role:admin,superadmin');
        Route::get('map', [AdminController::class, 'map']);
        Route::get('map/data', [AdminController::class, 'mapData']);
        Route::post('map/ping-customer', [AdminController::class, 'pingCustomer']);
        Route::post('map/update-coords', [AdminController::class, 'updateCoords'])->middleware('role:admin,superadmin');
        Route::post('map/update-cable-path', [AdminController::class, 'updateCablePath'])->middleware('role:admin,superadmin');
        Route::get('api/onu-locations', [ApiController::class, 'onuLocations']);
        Route::get('odp', [AdminController::class, 'odp']);
        Route::get('odp/new', [AdminController::class, 'odp'])->defaults('create', 1);
        Route::post('odp/add', [AdminController::class, 'addOdp'])->middleware('role:admin,superadmin');
        Route::post('odp/edit/{id}', [AdminController::class, 'editOdp'])->middleware('role:admin,superadmin');
        Route::match(['delete', 'post'], 'odp/delete/{id}', [AdminController::class, 'deleteOdp'])->middleware('role:admin,superadmin');
        Route::get('pppoe', [PppoeController::class, 'index']);
        Route::post('pppoe/kick', [PppoeController::class, 'kick'])->middleware('role:admin,superadmin');
        Route::post('pppoe/delete-secret', [PppoeController::class, 'deleteSecret'])->middleware('role:admin,superadmin');
        Route::get('arp', [ArpController::class, 'index']);
        Route::get('arp-binding', fn () => redirect('/admin/arp'));
        Route::post('arp/store', [ArpController::class, 'store'])->middleware('role:admin,superadmin');
        Route::post('arp/add-static', [ArpController::class, 'store'])->middleware('role:admin,superadmin');
        Route::post('arp/make-static', [ArpController::class, 'makeStatic'])->middleware('role:admin,superadmin');
        Route::post('arp/toggle', [ArpController::class, 'toggle'])->middleware('role:admin,superadmin');
        Route::post('arp/toggle-disabled', [ArpController::class, 'toggle'])->middleware('role:admin,superadmin');
        Route::post('arp/delete', [ArpController::class, 'delete'])->middleware('role:admin,superadmin');
        Route::match(['get', 'post'], 'arp/ping', [ArpController::class, 'ping'])->middleware('role:admin,superadmin');
        Route::match(['get', 'post'], 'arp/ping-test', [ArpController::class, 'ping'])->middleware('role:admin,superadmin');
        Route::get('collector-payments', fn () => redirect('/dashboard'));
        Route::get('promo-slides', fn () => redirect('/dashboard'));
        Route::get('promo-slides/new', fn () => redirect('/dashboard'));
        Route::post('promo-slides/store', [AdminController::class, 'promoSlidesStore'])->middleware('role:admin,superadmin');
        Route::post('promo-slides/delete/{id}', [AdminController::class, 'promoSlidesDelete'])->middleware('role:admin,superadmin');
        Route::get('sidebar-settings', [AdminController::class, 'sidebarSettings']);
        Route::post('sidebar-settings/save', [AdminController::class, 'sidebarSettingsSave'])->middleware('role:admin,superadmin');
        Route::post('dashboard-menus/save', [\App\Http\Controllers\DashboardController::class, 'saveMenus'])->middleware('role:admin,superadmin');

        // Add-ons
        Route::get('addons', [AddonController::class, 'index']);
        Route::post('addons/buy/{id}', [AddonController::class, 'buy'])->middleware('role:admin,superadmin');
        Route::post('addons/toggle-auto-renew/{id}', [AddonController::class, 'toggleAutoRenew'])->middleware('role:admin,superadmin');
        Route::post('addons/toggle/{id}', [AddonController::class, 'toggle'])->middleware('role:admin,superadmin');
        Route::post('addons/cancel/{id}', [AddonController::class, 'cancel'])->middleware('role:admin,superadmin');
        Route::post('addons/configure/{id}', [AddonController::class, 'configure'])->middleware('role:admin,superadmin');
        Route::get('cashier-reports', [CashierController::class, 'reports']);
        Route::get('agent-reports', [AdminController::class, 'agentReports']);
        Route::get('print-payslip', [AdminController::class, 'printPayslip']);
        Route::get('print-vouchers', [AdminController::class, 'printVouchers']);
        Route::get('reports', [AdminController::class, 'reports']);
        Route::get('reports/print', [AdminController::class, 'reportsPrint']);
        Route::get('voucher-packages', fn () => redirect('/admin/voucher'));
        Route::post('voucher-packages/add', [AdminController::class, 'voucherPackageAdd'])->middleware('role:admin,superadmin');
        Route::post('voucher-packages/edit/{id}', [AdminController::class, 'voucherPackageEdit'])->middleware('role:admin,superadmin');
        Route::post('voucher-packages/delete/{id}', [AdminController::class, 'voucherPackageDelete'])->middleware('role:admin,superadmin');

        // === ADMIN-ONLY ROUTES ===
        Route::middleware('role:admin,superadmin')->group(function () {
            // MikroTik
            Route::get('mikrotik', fn () => redirect('/admin/mikrotik/routers'));
            Route::get('mikrotik/routers', [MikrotikController::class, 'routers']);
            Route::post('mikrotik/router/add', [MikrotikController::class, 'addRouter']);
            Route::post('mikrotik/router/edit/{id}', [MikrotikController::class, 'editRouter']);
            Route::post('mikrotik/router/delete/{id}', [MikrotikController::class, 'deleteRouter']);
            Route::get('mikrotik/router/test/{id}', [MikrotikController::class, 'testRouter']);
            Route::post('mikrotik/router/test-live', [MikrotikController::class, 'testRouterAjax']);
            Route::get('mikrotik/router/backup/{id}', [MikrotikController::class, 'backupRouter']);
            Route::get('mikrotik/router/{id}', [MikrotikController::class, 'viewRouter']);
            Route::get('mikrotik/router/{id}/traffic', [MikrotikController::class, 'interfaceTraffic']);
            Route::get('mikrotik/profiles', [AdminController::class, 'mikrotikProfiles']);
            Route::get('mikrotik/hotspot-profiles', fn () => redirect('/admin/mikrotik/profiles'));
            Route::post('mikrotik/action', [AdminController::class, 'mikrotikAction']);
            Route::get('mikrotik/display', [AdminController::class, 'mikrotikDisplay']);
            Route::get('hotspot', [AdminController::class, 'hotspot']);

            // RADIUS Server / Client Management
            Route::prefix('radius')->name('radius.')->group(function () {
                Route::get('/', [RadiusController::class, 'index'])->name('index');
                Route::post('settings', [RadiusController::class, 'saveSettings'])->name('settings');
                Route::post('test-connection', [RadiusController::class, 'testConnection'])->name('test');
                Route::post('sync-all', [RadiusController::class, 'syncAll'])->name('sync');
                Route::post('nas/save', [RadiusController::class, 'saveNas'])->name('nas.save');
                Route::post('nas/delete/{id}', [RadiusController::class, 'deleteNas'])->name('nas.delete');
                Route::post('disconnect-session', [RadiusController::class, 'disconnectSession'])->name('disconnect');
            });

            // Legacy Voucher Redirect to Mikhmon
            Route::get('voucher', fn () => redirect('/admin/mikhmon'));
            Route::get('voucher/{any}', fn () => redirect('/admin/mikhmon'))->where('any', '.*');

            // Dedicated Tenant MIKHMON Panel
            Route::prefix('mikhmon')->name('mikhmon.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\AdminMikhmonController::class, 'index'])->name('index');
                Route::get('open', [\App\Http\Controllers\Admin\AdminMikhmonController::class, 'open'])->name('open');
                Route::get('live-income', [\App\Http\Controllers\Admin\AdminMikhmonController::class, 'liveIncome'])->name('live-income');
                Route::post('switch-ros-version', [\App\Http\Controllers\Admin\AdminMikhmonController::class, 'switchRosVersion'])->name('switch-ros');
                Route::post('reset-password', [\App\Http\Controllers\Admin\AdminMikhmonController::class, 'resetPassword'])->name('reset-password');
            });

            // Lisensi Mikhmon Desktop Standalone (Offline)
            Route::get('desktop-licenses', [\App\Http\Controllers\Admin\TenantDesktopLicenseController::class, 'index'])->name('desktop-licenses.index');
            Route::get('mikhmon-offline', [\App\Http\Controllers\Admin\TenantDesktopLicenseController::class, 'index'])->name('mikhmon-offline.index');

            // OLT Management
            Route::prefix('olt')->name('olt.')->group(function () {
                Route::get('/', [OltController::class, 'index']);
                Route::get('new', [OltController::class, 'index'])->defaults('create', 1);
                Route::post('add', [OltController::class, 'add'])->name('add');
                Route::post('store', [OltController::class, 'add'])->name('store');
                Route::post('test-connection', [OltController::class, 'testConnection'])->name('test-connection');
                Route::match(['put', 'post'], 'edit/{id}', [OltController::class, 'edit'])->name('edit');
                Route::match(['put', 'post'], 'update/{id}', [OltController::class, 'edit'])->name('update');
                Route::match(['delete', 'post'], 'delete/{id}', [OltController::class, 'delete'])->name('delete');
                Route::post('sync/{id}', [OltController::class, 'sync']);
                Route::get('test/{id}', [OltController::class, 'test']);
                Route::post('reboot/{id}', [OltController::class, 'reboot']);
                Route::get('onus/{id}', [OltController::class, 'onus']);
                Route::match(['delete', 'post'], 'onus/{id}/delete/{onuId}', [OltController::class, 'deleteOnu']);
                Route::post('onus/{id}/link-customer/{onuId}', [OltController::class, 'linkCustomer']);
                Route::post('onus/{id}/reboot/{onuId}', [OltController::class, 'rebootOnu']);
                Route::get('provision/{olt}', [OltController::class, 'provision']);
                Route::post('scan-onus/{id}', [OltController::class, 'scanOnus']);
                Route::post('provision-onu/{id}', [OltController::class, 'provisionOnu']);
            });

            // Trouble Tickets
            Route::get('trouble', [AdminController::class, 'trouble']);
            Route::get('tickets', [AdminController::class, 'trouble']);
            Route::redirect('work-orders', '/dashboard');
            Route::post('trouble/create', [AdminController::class, 'createTicket']);
            Route::post('trouble/update/{id}', [AdminController::class, 'updateTicket']);
            Route::post('trouble/assign/{id}', [AdminController::class, 'assignTicket']);
            Route::post('trouble/close/{id}', [AdminController::class, 'closeTicket']);
            Route::post('trouble/take/{id}', [AdminController::class, 'takeTicket']);

            // Billing
            Route::prefix('billing')->name('billing.')->group(function () {
                Route::get('invoices', [BillingController::class, 'invoices']);
                Route::get('invoices/export-csv', [BillingController::class, 'exportInvoicesCsv']);
                Route::post('trigger-isolation', [BillingController::class, 'triggerAutoIsolation']);
                Route::post('trigger-reminders', [BillingController::class, 'triggerWaReminders']);
                Route::get('customers', [BillingController::class, 'customers']);
                Route::get('customers/new', [BillingController::class, 'customers'])->defaults('create', 1);
                Route::get('customers/export', [BillingController::class, 'exportCustomers']);
                Route::get('packages', [BillingController::class, 'packages']);
                Route::get('packages/new', [BillingController::class, 'packages'])->defaults('create', 1);
                Route::post('packages/add', [BillingController::class, 'addPackage']);
                Route::match(['post', 'put'], 'packages/update/{id}', [BillingController::class, 'updatePackage']);
                Route::match(['post', 'put'], 'packages/edit/{id}', [BillingController::class, 'updatePackage']);
                Route::post('packages/delete/{id}', [BillingController::class, 'deletePackage']);
                Route::post('packages/delete-batch', [BillingController::class, 'deletePackageBatch']);
                Route::post('packages/delete-all', [BillingController::class, 'deleteAllPackages']);
                Route::post('packages/sync', [BillingController::class, 'syncPackages']);
                Route::get('packages/router-profiles/{routerId}', [BillingController::class, 'getRouterProfiles']);
                Route::post('packages/auto-isolir/{id}', [BillingController::class, 'toggleAutoIsolir']);
                Route::post('generate', [BillingController::class, 'generateInvoices']);
                Route::get('auto-invoice-settings', [BillingController::class, 'getAutoInvoiceSettings']);
                Route::post('auto-invoice-settings', [BillingController::class, 'saveAutoInvoiceSettings']);
                Route::post('generate-now', [BillingController::class, 'generateInvoicesNow']);
                Route::post('advance-payment', [BillingController::class, 'advancePayment']);
                Route::post('invoices/advance-payment', [BillingController::class, 'advancePayment']);
                Route::post('pay/{id}', [BillingController::class, 'payInvoice']);
                Route::post('invoices/pay/{id}', [BillingController::class, 'payInvoice']);
                Route::post('cancel/{id}', [BillingController::class, 'cancelInvoice']);
                Route::post('invoices/cancel/{id}', [BillingController::class, 'cancelInvoice']);
                Route::post('unpay/{id}', [BillingController::class, 'cancelInvoice']);
                Route::post('invoices/unpay/{id}', [BillingController::class, 'cancelInvoice']);
                Route::post('delete-invoice/{id}', [BillingController::class, 'deleteInvoice']);
                Route::post('invoices/delete/{id}', [BillingController::class, 'deleteInvoice']);
                Route::post('delete-all-invoices', [BillingController::class, 'deleteAllInvoices'])->middleware('role:admin,superadmin');
                Route::post('reset-invoices', [BillingController::class, 'resetInvoices'])->middleware('role:admin,superadmin');
                Route::post('unisolate_only/{id}', [BillingController::class, 'unisolateOnly']);
                Route::get('print/{id}', [BillingController::class, 'printInvoice']);
                Route::get('invoices/print/{id}', [BillingController::class, 'printInvoice']);
                Route::get('invoices/{id}/print', [BillingController::class, 'printInvoice']);
                Route::get('invoices/print-thermal', [BillingController::class, 'printThermal']);
                Route::get('invoices/print-thermal/{id}', [BillingController::class, 'printThermal']);
                Route::get('print-thermal', [BillingController::class, 'printThermal']);
                Route::get('print-thermal/{id}', [BillingController::class, 'printThermal']);
                Route::get('cron/isolir', [BillingController::class, 'checkIsolation']);
                Route::post('pay-batch', [BillingController::class, 'payBatch']);
                Route::post('cancel-batch', [BillingController::class, 'cancelBatch']);
                Route::post('delete-batch', [BillingController::class, 'deleteBatch']);
                Route::post('invoices/{id}/send-wa', [BillingController::class, 'sendWaReminder']);
                Route::post('invoices/send-wa-batch', [BillingController::class, 'sendWaReminderBatch']);
                // Customer actions
                Route::get('customers/template', [BillingController::class, 'downloadTemplate']);
                Route::post('customers/import', [BillingController::class, 'importCustomers']);
                Route::post('import', [BillingController::class, 'importCustomers']);
                Route::post('customers/add', [BillingController::class, 'addCustomer']);
                Route::get('customers/get/{id}', [BillingController::class, 'getCustomer']);
                Route::post('customers/edit/{id}', [BillingController::class, 'editCustomer']);
                Route::post('delete-customer/{id}', [BillingController::class, 'deleteCustomer']);
                Route::post('customers/delete/{id}', [BillingController::class, 'deleteCustomer']);
                Route::post('customers/delete-batch', [BillingController::class, 'deleteCustomerBatch']);
                Route::post('customers/bulk-delete-selected', [BillingController::class, 'deleteCustomerBatch']);
                Route::post('customers/delete-all', [BillingController::class, 'deleteAllCustomers']);
                Route::post('customers/sync', [BillingController::class, 'syncCustomers']);
                Route::post('customers/reprovision', [BillingController::class, 'reprovisionToMikrotik']);
                Route::post('customers/reset-pin/{id}', [BillingController::class, 'resetPin']);
                Route::post('customers/isolir/{id}', [BillingController::class, 'isolateCustomer']);
                Route::post('customers/isolate/{id}', [BillingController::class, 'isolateCustomer']);
                Route::post('customers/unisolate/{id}', [BillingController::class, 'unisolateCustomer']);
                Route::post('customers/unisolate-batch', [BillingController::class, 'unisolateCustomerBatch']);
                Route::post('customers/isolate-batch', [BillingController::class, 'isolateCustomerBatch']);
            });

            // Employees
            Route::prefix('employees')->name('employees.')->group(function () {
                Route::get('/', [EmployeeController::class, 'index']);
                Route::get('new', [EmployeeController::class, 'index'])->defaults('create', 1);
                Route::post('add', [EmployeeController::class, 'add']);
                Route::post('edit/{id}', [EmployeeController::class, 'edit']);
                Route::post('delete/{id}', [EmployeeController::class, 'delete']);
                Route::post('permissions/save', [EmployeeController::class, 'savePermissions']);
                Route::post('permissions/{id}', [EmployeeController::class, 'saveEmployeePermissions']);
            });

            // Attendance
            Route::get('attendance', [AttendanceController::class, 'index']);
            Route::get('attendance/report', [AttendanceController::class, 'report']);
            Route::post('attendance/checkin', [AttendanceController::class, 'checkin']);
            Route::post('attendance/checkout/{id}', [AttendanceController::class, 'checkout']);

            // Inventory
            Route::prefix('inventory')->name('inventory.')->group(function () {
                Route::get('/', [InventoryController::class, 'index']);
                Route::get('new', [InventoryController::class, 'index'])->defaults('create', 1);
                Route::post('category/add', [InventoryController::class, 'addCategory']);
                Route::post('category/edit/{id}', [InventoryController::class, 'editCategory']);
                Route::post('category/delete/{id}', [InventoryController::class, 'deleteCategory']);
                Route::post('item/add', [InventoryController::class, 'addItem']);
                Route::post('item/edit/{id}', [InventoryController::class, 'editItem']);
                Route::post('item/delete/{id}', [InventoryController::class, 'deleteItem']);
                Route::post('item/adjust/{id}', [InventoryController::class, 'adjustStock']);
            });

            // Agents
            Route::prefix('agents')->name('agents.')->group(function () {
                Route::get('/', fn () => redirect('/admin/employees'));
                Route::post('add', [AgentController::class, 'add']);
                Route::post('edit/{id}', [AgentController::class, 'edit']);
                Route::post('delete/{id}', [AgentController::class, 'delete']);
                Route::post('topup/{id}', [AgentController::class, 'topup']);
            });

            // Collectors (manajemen dialihkan ke Karyawan)
            Route::prefix('collectors')->name('collectors.')->group(function () {
                Route::get('/', fn () => redirect('/admin/employees'));
                Route::get('new', fn () => redirect('/admin/employees/new'));
                Route::post('add', [CollectorController::class, 'add']);
                Route::post('edit/{id}', [CollectorController::class, 'edit']);
                Route::post('delete/{id}', [CollectorController::class, 'delete']);
            });

            // Technicians (manajemen)
            Route::prefix('technicians')->name('technicians.')->group(function () {
                Route::get('/', fn () => redirect('/admin/employees'));
                Route::post('add', [TechnicianController::class, 'add']);
                Route::post('update/{id}', [TechnicianController::class, 'update']);
                Route::post('delete/{id}', [TechnicianController::class, 'delete']);
            });

            // Payroll & Laporan Transaksi dihapus — arahkan ke Laporan Keuangan
            Route::redirect('payroll', '/admin/finance');
            Route::redirect('reports', '/admin/finance');
            Route::redirect('cashier-reports', '/admin/finance');
            Route::redirect('agent-reports', '/admin/finance');
            Route::redirect('collector-payments', '/admin/finance');

            // API Apps
            Route::get('api-apps', [SettingsController::class, 'apiApps']);

            // Top Bandwidth (live dari MikroTik)
            Route::prefix('top-bandwidth')->name('top-bandwidth.')->group(function () {
                Route::get('/', [TopBandwidthController::class, 'index']);
                Route::get('data', [TopBandwidthController::class, 'data']);
            });

            // Monitoring server dihapus — arahkan ke halaman MikroTik
            // (detail resource system router).
            Route::redirect('monitoring', '/admin/mikrotik/routers');

            // Payments
            Route::prefix('payments')->name('payments.')->group(function () {
                Route::get('gateway', [PaymentGatewayController::class, 'index']);
                Route::post('gateway/usage/save', [PaymentGatewayController::class, 'saveUsage']);
                Route::post('gateway/test/{gateway}', [PaymentGatewayController::class, 'testGateway']);
                Route::post('gateway/noderapay/save', [PaymentGatewayController::class, 'saveNoderapay']);
                Route::post('gateway/cinetpay/save', [PaymentGatewayController::class, 'saveCinetPay']);
                Route::post('gateway/wave/save', [PaymentGatewayController::class, 'saveWave']);
                Route::post('gateway/paytech/save', [PaymentGatewayController::class, 'savePayTech']);
                Route::post('gateway/fedapay/save', [PaymentGatewayController::class, 'saveFedaPay']);
                Route::post('gateway/midtrans/save', [PaymentGatewayController::class, 'saveMidtrans']);
                Route::post('gateway/doku/save', [PaymentGatewayController::class, 'saveDoku']);
                Route::post('gateway/tripay/save', [PaymentGatewayController::class, 'saveTripay']);
                Route::post('gateway/xendit/save', [PaymentGatewayController::class, 'saveXendit']);
                Route::post('gateway/duitku/save', [PaymentGatewayController::class, 'saveDuitku']);
                Route::post('gateway/wijayapay/save', [PaymentGatewayController::class, 'saveWijayapay']);
                Route::post('gateway/paydisini/save', [PaymentGatewayController::class, 'savePaydisini']);
                Route::post('gateway/pakasir/save', [PaymentGatewayController::class, 'savePakasir']);
                Route::post('gateway/toggle/{gateway}', [PaymentGatewayController::class, 'toggleGateway']);
                Route::post('gateway/{gateway}/delete', [PaymentGatewayController::class, 'deleteGateway']);
                // Redirect legacy static QRIS to universal Payment Gateway
                Route::get('qris', function () {
                    return redirect()->route('admin.payments.gateway');
                })->name('qris');
            });

            // Payment Gateways Alternate / Legacy Aliases
            Route::prefix('payment-gateways')->group(function () {
                Route::get('/', [PaymentGatewayController::class, 'index']);
                Route::post('usage', [PaymentGatewayController::class, 'saveUsage']);
                Route::post('usage/save', [PaymentGatewayController::class, 'saveUsage']);
                Route::post('test/{gateway}', [PaymentGatewayController::class, 'testGateway']);
                Route::post('{gateway}/toggle-status', [PaymentGatewayController::class, 'toggleGateway']);
                Route::post('toggle/{gateway}', [PaymentGatewayController::class, 'toggleGateway']);
                Route::delete('{gateway}', [PaymentGatewayController::class, 'deleteGateway']);
                Route::post('{gateway}/delete', [PaymentGatewayController::class, 'deleteGateway']);
                Route::post('noderapay', [PaymentGatewayController::class, 'saveNoderapay']);
                Route::post('wijayapay', [PaymentGatewayController::class, 'saveWijayapay']);
                Route::post('tripay', [PaymentGatewayController::class, 'saveTripay']);
                Route::post('midtrans', [PaymentGatewayController::class, 'saveMidtrans']);
                Route::post('duitku', [PaymentGatewayController::class, 'saveDuitku']);
                Route::post('xendit', [PaymentGatewayController::class, 'saveXendit']);
                Route::post('paydisini', [PaymentGatewayController::class, 'savePaydisini']);
                Route::post('pakasir', [PaymentGatewayController::class, 'savePakasir']);
            });

            Route::prefix('payment-gateway')->group(function () {
                Route::get('/', [PaymentGatewayController::class, 'index']);
                Route::post('usage/save', [PaymentGatewayController::class, 'saveUsage']);
                Route::post('test/{gateway}', [PaymentGatewayController::class, 'testGateway']);
                Route::post('toggle/{gateway}', [PaymentGatewayController::class, 'toggleGateway']);
                Route::post('{gateway}/delete', [PaymentGatewayController::class, 'deleteGateway']);
                Route::post('noderapay', [PaymentGatewayController::class, 'saveNoderapay']);
                Route::post('wijayapay', [PaymentGatewayController::class, 'saveWijayapay']);
                Route::post('tripay', [PaymentGatewayController::class, 'saveTripay']);
                Route::post('midtrans', [PaymentGatewayController::class, 'saveMidtrans']);
                Route::post('duitku', [PaymentGatewayController::class, 'saveDuitku']);
                Route::post('xendit', [PaymentGatewayController::class, 'saveXendit']);
                Route::post('paydisini', [PaymentGatewayController::class, 'savePaydisini']);
                Route::post('pakasir', [PaymentGatewayController::class, 'savePakasir']);
            });

            // QRIS legacy alias redirects
            Route::get('qris', function () {
                return redirect()->route('admin.payments.gateway');
            });

            // Expenses & Kas Operasional
            Route::prefix('expenses')->name('expenses.')->group(function () {
                Route::get('/', [FinanceController::class, 'expenses']);
                Route::get('new', [FinanceController::class, 'expenses'])->defaults('create', 1);
                Route::post('/', [FinanceController::class, 'storeExpense']);
                Route::post('add', [FinanceController::class, 'storeExpense']);
                Route::get('{id}/edit', [FinanceController::class, 'editExpense']);
                Route::post('edit/{id}', [FinanceController::class, 'updateExpense']);
                Route::post('{id}/update', [FinanceController::class, 'updateExpense']);
                Route::post('{id}/delete', [FinanceController::class, 'deleteExpense']);
                Route::post('delete/{id}', [FinanceController::class, 'deleteExpense']);
            });

            // Finance
            Route::prefix('finance')->name('finance.')->group(function () {
                Route::get('/', [FinanceController::class, 'index']);
                Route::get('print', [FinanceController::class, 'printReport']);
                Route::get('export-csv', [FinanceController::class, 'exportCsv']);
                Route::get('export-commission-csv', [FinanceController::class, 'exportCollectorCommissionCsv']);
                Route::get('export-pdf', [FinanceController::class, 'exportPdf']);
                Route::get('expenses', [FinanceController::class, 'expenses']);
                Route::get('expenses/new', [FinanceController::class, 'expenses'])->defaults('create', 1);
                Route::post('expenses', [FinanceController::class, 'storeExpense']);
                Route::post('expenses/add', [FinanceController::class, 'storeExpense']);
                Route::get('expenses/{id}/edit', [FinanceController::class, 'editExpense']);
                Route::post('expenses/edit/{id}', [FinanceController::class, 'updateExpense']);
                Route::post('expenses/{id}/update', [FinanceController::class, 'updateExpense']);
                Route::post('expenses/{id}/delete', [FinanceController::class, 'deleteExpense']);
                Route::post('expenses/delete/{id}', [FinanceController::class, 'deleteExpense']);
            });
        }); // end role:admin,superadmin

        // Backup — khusus superadmin
        Route::prefix('backup')->name('backup.')->middleware('role:superadmin')->group(function () {
            Route::get('/', [BackupController::class, 'index']);
            Route::post('create', [BackupController::class, 'runBackup']);
            Route::get('download/{file}', [BackupController::class, 'downloadBackup']);
            Route::post('delete/{file}', [BackupController::class, 'deleteBackup']);
        });
    }); // end admin group
}); // end auth.or.tech

