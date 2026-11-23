<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\BillCategoryController;
use App\Http\Controllers\Admin\BillController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════
// ADMIN ROUTES (admin role only)
// ═══════════════════════════════════════════════════

Route::get('/', function () {
    return redirect('/admin');
});

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('pages-home');

    // ── The signed-in admin's own account ────────────
    Route::get('profile', [AccountController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [AccountController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [AccountController::class, 'updatePassword'])->name('profile.password');

    // ── User management ──────────────────────────────
    Route::get('user/list', [UserController::class, 'index'])->name('app-user-list');
    Route::get('user/create', [UserController::class, 'create'])->name('app-user-create');
    Route::post('user/store', [UserController::class, 'store'])->name('app-user-store');
    Route::get('user/view/{user}', [UserController::class, 'show'])->name('app-user-view');
    Route::get('user/edit/{user}', [UserController::class, 'edit'])->name('app-user-edit');
    Route::put('user/update/{user}', [UserController::class, 'update'])->name('app-user-update');
    Route::patch('user/status/{user}', [UserController::class, 'toggleStatus'])->name('app-user-status');
    Route::delete('user/delete/{user}', [UserController::class, 'destroy'])->name('app-user-delete');

    // ── Bills oversight ──────────────────────────────
    Route::get('bills', [BillController::class, 'index'])->name('admin-bills');
    Route::get('bills/{bill}', [BillController::class, 'show'])->name('admin-bill-view');

    // ── Bill categories ──────────────────────────────
    Route::get('categories', [BillCategoryController::class, 'index'])->name('admin-categories');
    Route::post('categories', [BillCategoryController::class, 'store'])->name('admin-category-store');
    Route::put('categories/{category}', [BillCategoryController::class, 'update'])->name('admin-category-update');
    Route::delete('categories/{category}', [BillCategoryController::class, 'destroy'])->name('admin-category-delete');

    // ── Plans & subscriptions ────────────────────────
    Route::get('plans', [PlanController::class, 'index'])->name('admin-plans');
    Route::get('plans/{plan}/edit', [PlanController::class, 'edit'])->name('admin-plan-edit');
    Route::put('plans/{plan}', [PlanController::class, 'update'])->name('admin-plan-update');

    Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('admin-subscriptions');
    Route::post('subscriptions/grant', [SubscriptionController::class, 'grant'])->name('admin-subscription-grant');
    Route::patch('subscriptions/{subscription}/revoke', [SubscriptionController::class, 'revoke'])->name('admin-subscription-revoke');
    Route::post('subscriptions/users/{user}/resync', [SubscriptionController::class, 'resync'])->name('admin-subscription-resync');

    // ── Settings ─────────────────────────────────────
    Route::get('settings', [SettingController::class, 'index'])->name('admin-settings');
    Route::put('settings', [SettingController::class, 'update'])->name('admin-settings-update');
});
