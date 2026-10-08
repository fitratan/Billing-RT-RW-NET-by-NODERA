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


