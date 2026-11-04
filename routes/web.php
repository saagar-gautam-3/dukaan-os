<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\PurchaseController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\SupplierController;
use App\Http\Controllers\Platform\PlatformShopController;
use App\Http\Controllers\Product\CategoryController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Product\UnitController;
use App\Http\Controllers\Reports\CashSessionController;
use App\Http\Controllers\Reports\ExpenseCategoryController;
use App\Http\Controllers\Reports\ExpenseController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\PaymentController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Shop\ShopPickerController;
use App\Http\Controllers\Shop\ShopSettingsController;
use App\Http\Controllers\Udhar\UdharController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public homepage
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Language switcher
Route::get('/locale/{locale}', function (Request $request, string $locale) {
    if (in_array($locale, ['en', 'ne'], true)) {
        session(['locale' => $locale]);
        if ($request->user()) {
            $request->user()->update(['locale' => $locale]);
        }
        cookie()->queue('locale', $locale, 60 * 24 * 365);
    }

    return back();
})->name('locale.switch');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:password-reset');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store')->middleware('throttle:password-reset');
});

// Authenticated User Routes (Shop Switching & Logout)
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/shops/picker', [ShopPickerController::class, 'index'])->name('shops.picker');
    Route::post('/shops/switch/{shop}', [ShopPickerController::class, 'switch'])->name('shops.switch');
    Route::middleware('verified')->group(function (): void {
        Route::get('/shops/create', [ShopPickerController::class, 'create'])->name('shops.create');
        Route::post('/shops', [ShopPickerController::class, 'store'])->name('shops.store');
    });
});

// Tenant-Scoped App Routes
Route::middleware(['auth', 'verified', 'tenant.resolve', 'shop.access'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/settings/shop', [ShopSettingsController::class, 'edit'])->name('settings.shop');
    Route::put('/settings/shop', [ShopSettingsController::class, 'update'])->name('settings.shop.update');

    // Products, Categories, Units
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('categories', CategoryController::class)->except(['create', 'show', 'edit']);
    Route::resource('units', UnitController::class)->only(['index']);

    // Stock & Inventory
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock/adjust', [StockController::class, 'adjustForm'])->name('stock.adjust');
    Route::post('/stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust.store');
    Route::get('/stock/alerts', [StockController::class, 'alerts'])->name('stock.alerts');
    Route::get('/stock/history/{product}', [StockController::class, 'history'])->name('stock.history');

    // Suppliers
    Route::resource('suppliers', SupplierController::class)->except(['show']);

    // Purchases
    Route::resource('purchases', PurchaseController::class)->except(['edit', 'update']);
    Route::post('/purchases/{purchase}/post', [PurchaseController::class, 'post'])->name('purchases.post');

    // Sales & POS
    Route::get('/pos', [SaleController::class, 'pos'])->name('pos');
    Route::get('/pos/products', [SaleController::class, 'searchProducts'])->name('pos.products');
    Route::get('/pos/customers', [SaleController::class, 'searchCustomers'])->name('pos.customers');
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store')->middleware('throttle:post-sale');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::post('/sales/{sale}/post', [SaleController::class, 'post'])->name('sales.post')->middleware('throttle:post-sale');
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    Route::get('/sales/{sale}/return', [SaleController::class, 'returnForm'])->name('sales.return');
    Route::post('/sales/{sale}/return', [SaleController::class, 'processReturn'])->name('sales.return.store');
    Route::get('/sales/{sale}/invoice', [SaleController::class, 'invoice'])->name('sales.invoice');
    Route::get('/sales/{sale}/thermal', [SaleController::class, 'thermal'])->name('sales.thermal');

    // Payments (QR verification)
    Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify')->middleware('throttle:payment');
    Route::post('/payments/{payment}/fail', [PaymentController::class, 'fail'])->name('payments.fail')->middleware('throttle:payment');

    // Customers
    Route::resource('customers', CustomerController::class);

    // Udhar Khata (Customer Ledger)
    Route::prefix('udhar')->group(function () {
        Route::get('/', [UdharController::class, 'outstanding'])->name('udhar.outstanding');
        Route::get('/ageing', [UdharController::class, 'ageing'])->name('udhar.ageing');
        Route::get('/collect/{customer}', [UdharController::class, 'collectForm'])->name('udhar.collect');
        Route::post('/collect/{customer}', [UdharController::class, 'collect'])->name('udhar.collect.store')->middleware('throttle:collect');
        Route::post('/remind/{customer}', [UdharController::class, 'remind'])->name('udhar.remind')->middleware('throttle:remind');
    });

    // Expenses
    Route::resource('expenses', ExpenseController::class)->except(['show']);
    Route::resource('expense-categories', ExpenseCategoryController::class)->except(['show']);

    // Cash Sessions
    Route::prefix('cash')->name('cash.')->group(function () {
        Route::get('/', [CashSessionController::class, 'index'])->name('index');
        Route::post('/open', [CashSessionController::class, 'open'])->name('open');
        Route::get('/{cashSession}', [CashSessionController::class, 'show'])->name('show');
        Route::post('/{cashSession}/close', [CashSessionController::class, 'close'])->name('close');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/profit', [ReportController::class, 'profitSummary'])->name('profit');
        Route::get('/profit/export', [ReportController::class, 'exportCsv'])->name('profit.export');
        Route::get('/stock', [ReportController::class, 'stockValuation'])->name('stock');
    });
});

// Platform admin (audited, no impersonation)
Route::middleware(['auth', 'platform.admin'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/shops', [PlatformShopController::class, 'index'])->name('shops.index');
    Route::post('/shops/{shop}/toggle', [PlatformShopController::class, 'toggle'])->name('shops.toggle');
});
