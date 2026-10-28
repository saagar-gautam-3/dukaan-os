<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
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
});
