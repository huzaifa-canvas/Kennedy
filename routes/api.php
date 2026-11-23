<?php

use App\Http\Controllers\Api\V1\AppContentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillController;
use App\Http\Controllers\Api\V1\BillOccurrenceController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\IncomeSourceController;
use App\Http\Controllers\Api\V1\InsightsController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PaycheckController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RevenueCatWebhookController;
use App\Http\Controllers\Api\V1\SavingsJarController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::fallback(function () {
    return response()->json([
        'status' => false,
        'message' => 'API route not found.',
    ], 404);
});

Route::prefix('v1')->group(function () {

    // ═══════════════════════════════════════════════════
    // PUBLIC — no token required
    // ═══════════════════════════════════════════════════
    Route::middleware('throttle:public')->group(function () {
        Route::get('app/config', [AppContentController::class, 'config']);
        Route::get('app/plans', [AppContentController::class, 'plans']);
        Route::get('app/bill-categories', [AppContentController::class, 'billCategories']);
    });

    // ═══════════════════════════════════════════════════
    // AUTHENTICATION
    // ═══════════════════════════════════════════════════
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', [AuthController::class, 'register']);
            Route::post('login', [AuthController::class, 'login']);
        });

        // Password reset by one-time code: send → verify → set.
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:password-reset');

        Route::middleware('throttle:otp')->group(function () {
            Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
            Route::post('reset-password', [AuthController::class, 'resetPassword']);
        });
    });

    // ═══════════════════════════════════════════════════
    // SUBSCRIPTION WEBHOOK — secret checked in the controller
    // ═══════════════════════════════════════════════════
    Route::post('webhooks/revenuecat', [RevenueCatWebhookController::class, 'handle'])
        ->middleware('throttle:webhook');

    // ═══════════════════════════════════════════════════
    // AUTHENTICATED
    // ═══════════════════════════════════════════════════
    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {

        // ── Session ──────────────────────────────────────
        Route::prefix('auth')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::put('password', [AuthController::class, 'changePassword'])
                ->middleware('throttle:writes');
        });

        // ── Onboarding (screens 05-07) ───────────────────
        Route::prefix('onboarding')->group(function () {
            Route::get('quick-picks', [OnboardingController::class, 'quickPicks']);
            Route::post('/', [OnboardingController::class, 'store'])->middleware('throttle:writes');
            Route::post('complete', [OnboardingController::class, 'complete']);
        });

        // ── Profile, settings, account (screens 19-20) ───
        Route::get('profile', [ProfileController::class, 'show']);
        Route::post('profile', [ProfileController::class, 'update'])->middleware('throttle:writes');
        Route::get('settings', [ProfileController::class, 'settings']);
        Route::put('settings', [ProfileController::class, 'updateSettings']);
        Route::get('account/export', [ProfileController::class, 'export'])->middleware('throttle:writes');
        Route::delete('account', [ProfileController::class, 'destroy'])->middleware('throttle:writes');

        // ── Dashboard (screen 08) ────────────────────────
        Route::get('dashboard', [DashboardController::class, 'index']);

        // ── Bills (screens 10-11) ────────────────────────
        Route::get('bills/summary', [BillController::class, 'summary']);
        Route::get('bills', [BillController::class, 'index']);
        Route::post('bills', [BillController::class, 'store'])->middleware('throttle:writes');
        Route::get('bills/{bill}', [BillController::class, 'show']);
        Route::put('bills/{bill}', [BillController::class, 'update'])->middleware('throttle:writes');
        Route::delete('bills/{bill}', [BillController::class, 'destroy'])->middleware('throttle:writes');

        // ── Dated bill instances ─────────────────────────
        Route::get('bill-occurrences', [BillOccurrenceController::class, 'index']);
        Route::middleware('throttle:writes')->group(function () {
            Route::post('bill-occurrences/{occurrence}/pay', [BillOccurrenceController::class, 'pay']);
            Route::post('bill-occurrences/{occurrence}/unpay', [BillOccurrenceController::class, 'unpay']);
            Route::post('bill-occurrences/{occurrence}/skip', [BillOccurrenceController::class, 'skip']);
        });

        // ── Income (screen 09) ───────────────────────────
        Route::get('income-sources', [IncomeSourceController::class, 'index']);
        Route::post('income-sources', [IncomeSourceController::class, 'store'])->middleware('throttle:writes');
        Route::get('income-sources/{incomeSource}', [IncomeSourceController::class, 'show']);
        Route::put('income-sources/{incomeSource}', [IncomeSourceController::class, 'update'])->middleware('throttle:writes');
        Route::delete('income-sources/{incomeSource}', [IncomeSourceController::class, 'destroy'])->middleware('throttle:writes');

        Route::get('paychecks', [PaycheckController::class, 'index']);
        Route::post('paychecks', [PaycheckController::class, 'store'])->middleware('throttle:writes');

        // ── Savings jars (screens 13, 15, Edit Jar) ──────
        Route::get('savings-jars', [SavingsJarController::class, 'index']);
        Route::post('savings-jars', [SavingsJarController::class, 'store'])->middleware('throttle:writes');
        Route::get('savings-jars/{savingsJar}', [SavingsJarController::class, 'show']);
        Route::put('savings-jars/{savingsJar}', [SavingsJarController::class, 'update'])->middleware('throttle:writes');
        Route::delete('savings-jars/{savingsJar}', [SavingsJarController::class, 'destroy'])->middleware('throttle:writes');
        Route::middleware('throttle:writes')->group(function () {
            Route::post('savings-jars/{savingsJar}/funds', [SavingsJarController::class, 'addFunds']);
            Route::post('savings-jars/{savingsJar}/withdraw', [SavingsJarController::class, 'withdrawFunds']);
        });

        // ── Calendar (screen 12) ─────────────────────────
        Route::get('calendar', [CalendarController::class, 'index']);

        // ── Insights & My Plan ───────────────────────────
        Route::get('insights', [InsightsController::class, 'index']);
        Route::get('my-plan', [InsightsController::class, 'myPlan']);
        Route::get('transactions', [TransactionController::class, 'index']);

        // ── Notifications (screen 18) ────────────────────
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('read-all', [NotificationController::class, 'markAllRead']);
            Route::post('{id}/read', [NotificationController::class, 'markRead'])
                ->whereUuid('id');
            Route::delete('{id}', [NotificationController::class, 'destroy'])
                ->whereUuid('id');
        });

        // ── Push devices ─────────────────────────────────
        Route::post('devices', [DeviceController::class, 'store']);
        Route::delete('devices', [DeviceController::class, 'destroy']);

        // ── Subscription / paywall ───────────────────────
        // No cancel endpoint on purpose: cancelling happens in the App Store /
        // Google Play via `manage_subscription_url`.
        Route::get('subscription', [SubscriptionController::class, 'index']);
        Route::post('subscription/sync', [SubscriptionController::class, 'sync'])->middleware('throttle:writes');
        Route::get('subscription/plan-selection', [SubscriptionController::class, 'selection']);
        Route::post('subscription/plan-selection', [SubscriptionController::class, 'saveSelection'])->middleware('throttle:writes');
        Route::post('subscription/restore-archived', [SubscriptionController::class, 'restore'])->middleware('throttle:writes');
    });
});
