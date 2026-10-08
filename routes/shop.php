<?php

use App\Http\Controllers\ShopController;
use App\Http\Controllers\TenantShopController;
use Illuminate\Support\Facades\Route;

// ==================== BOUTIQUE & E-COMMERCE PUBLIC ====================
Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::match(['get', 'post'], '/shop/preview-hotspot/{themePath}/{page?}', [ShopController::class, 'previewHotspot'])->where('themePath', '.*')->name('shop.hotspot-preview');
Route::match(['get', 'post'], '/hotspot-preview/{themePath}/{page?}', [ShopController::class, 'previewHotspot'])->where('themePath', '.*');
Route::match(['get', 'post'], '/preview-hotspot/{themePath}/{page?}', [ShopController::class, 'previewHotspot'])->where('themePath', '.*');
Route::get('/shop/product/{id}', [ShopController::class, 'getProductDetail'])->name('shop.product.detail');
Route::get('/shop/checkout', [ShopController::class, 'checkout'])->name('shop.checkout');
Route::post('/shop/checkout', [ShopController::class, 'storeOrder'])->name('shop.checkout.store');
Route::get('/shop/order/{orderNumber}', [ShopController::class, 'orderSuccess'])->name('shop.order.success');

// ==================== TENANT LANDING & SHOP PUBLIC ====================
Route::get('/t/{tenant_slug}', [TenantShopController::class, 'index'])->name('tenant.shop.index');
Route::post('/tenant-shop/checkout', [TenantShopController::class, 'checkout'])->name('tenant.shop.checkout');
Route::get('/tenant-shop/order/{orderNumber}/status', [TenantShopController::class, 'getOrderStatus'])->name('tenant.shop.order.status');
Route::get('/shop/order/{orderNumber}/status', [TenantShopController::class, 'getOrderStatus'])->name('shop.order.status');
