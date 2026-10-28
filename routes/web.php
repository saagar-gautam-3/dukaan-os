<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\PurchaseController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\SupplierController;
use App\Http\Controllers\Product\CategoryController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Product\UnitController;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Shop\ShopPickerController;
use App\Http\Controllers\Shop\ShopSettingsController;
use Illuminate\Support\Facades\Route;

// Public homepage
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Language switcher
Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, ['en', 'ne'], true)) {
        session(['locale' => $locale]);
    }

    return back();
})->name('locale.switch');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

// Authenticated User Routes (Shop Switching & Logout)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/shops/picker', [ShopPickerController::class, 'index'])->name('shops.picker');
    Route::post('/shops/switch/{shop}', [ShopPickerController::class, 'switch'])->name('shops.switch');
});

// Tenant-Scoped App Routes
Route::middleware(['auth', 'tenant.resolve', 'shop.access'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/settings/shop', [ShopSettingsController::class, 'edit'])->name('settings.shop');
    Route::put('/settings/shop', [ShopSettingsController::class, 'update'])->name('settings.shop.update');

    // Products, Categories, Units
    Route::resource('products', ProductController::class);
    Route::resource('categories', CategoryController::class)->except(['create', 'show', 'edit']);
    Route::resource('units', UnitController::class)->only(['index', 'store', 'update']);

    // Stock & Inventory
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock/adjust', [StockController::class, 'adjustForm'])->name('stock.adjust');
    Route::post('/stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust.store');
    Route::get('/stock/alerts', [StockController::class, 'alerts'])->name('stock.alerts');
    Route::get('/stock/history/{product}', [StockController::class, 'history'])->name('stock.history');

    // Suppliers
    Route::resource('suppliers', SupplierController::class);

    // Purchases
    Route::resource('purchases', PurchaseController::class)->except(['edit', 'update']);
    Route::post('/purchases/{purchase}/post', [PurchaseController::class, 'post'])->name('purchases.post');

    // Sales & POS
    Route::get('/pos', [SaleController::class, 'pos'])->name('pos');
    Route::get('/pos/products', [SaleController::class, 'searchProducts'])->name('pos.products');
    Route::get('/pos/customers', [SaleController::class, 'searchCustomers'])->name('pos.customers');
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::post('/sales/{sale}/post', [SaleController::class, 'post'])->name('sales.post');
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    Route::get('/sales/{sale}/return', [SaleController::class, 'returnForm'])->name('sales.return');
    Route::post('/sales/{sale}/return', [SaleController::class, 'processReturn'])->name('sales.return.store');
    Route::get('/sales/{sale}/invoice', [SaleController::class, 'invoice'])->name('sales.invoice');
    Route::get('/sales/{sale}/thermal', [SaleController::class, 'thermal'])->name('sales.thermal');

    // Customers
    Route::resource('customers', CustomerController::class);
});
