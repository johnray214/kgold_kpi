<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/page-avatar/{pageId}', [DashboardController::class, 'pageAvatar'])->name('page.avatar');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/broadcasts', [DashboardController::class, 'broadcastsPage'])->name('broadcasts');
    Route::get('/sequences', [DashboardController::class, 'sequencesPage'])->name('sequences');
    Route::get('/botcake-ai', [DashboardController::class, 'botcakeAiPage'])->name('botcake.ai');
    Route::get('/orders', [DashboardController::class, 'orders'])->name('orders');

    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('/accounts/{user}', [AccountController::class, 'update'])->name('accounts.update');
    Route::post('/accounts/{user}/pages', [AccountController::class, 'assignPages'])->name('accounts.pages');
    Route::delete('/accounts/{user}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::post('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');

    Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
    Route::post('/settings/pos-shops', [DashboardController::class, 'addPosShop'])->name('settings.pos_shops.add');
    Route::delete('/settings/pos-shops/{posShop}', [DashboardController::class, 'deletePosShop'])->name('settings.pos_shops.delete');
    Route::post('/settings/pages', [DashboardController::class, 'addPage'])->name('settings.pages.add');
    Route::delete('/settings/pages/{page}', [DashboardController::class, 'deletePage'])->name('settings.pages.delete');
    Route::post('/settings/tokens', [DashboardController::class, 'saveTokens'])->name('settings.tokens');
    Route::post('/settings/bulk-tokens', [DashboardController::class, 'bulkFetchTokens'])->name('settings.bulk');
    Route::post('/settings/scan-tokens', [DashboardController::class, 'scanPersonalTokens'])->name('settings.scan');
    Route::post('/settings/import-scanned', [DashboardController::class, 'importScannedPages'])->name('settings.import_scanned');

    Route::get('/export/orders', [DashboardController::class, 'exportOrders'])->name('export.orders');
    Route::get('/export/broadcasts', [DashboardController::class, 'exportBroadcasts'])->name('export.broadcasts');
    Route::post('/sync', [DashboardController::class, 'sync'])->name('sync');
});
