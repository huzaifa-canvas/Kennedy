<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SubscriptionManager;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server subscription updates from RevenueCat.
 *
 * Guarded by the shared secret set as the Authorization header in the
 * RevenueCat dashboard, compared in constant time.
 *
 * The event is treated as a nudge: when the secret REST key is configured we
 * re-read the subscriber from RevenueCat (correct even if events arrive late
 * or out of order). Without it, the event payload itself is applied.
 *
 * Handled: INITIAL_PURCHASE, RENEWAL, CANCELLATION (auto-renew off, Pro kept
 * to period end), UNCANCELLATION, BILLING_ISSUE (Pro kept through the store's
 * grace / retry period), EXPIRATION (downgrade to Free), PRODUCT_CHANGE,
 * SUBSCRIPTION_EXTENDED, NON_RENEWING_PURCHASE and TRANSFER.
 */
class RevenueCatWebhookController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly SubscriptionManager $subscriptions)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.revenuecat.webhook_secret');

        if (! $secret) {
            Log::warning('RevenueCat webhook hit with no secret configured.');

            return $this->fail('Webhook is not configured.', 503);
        }

        if (! hash_equals($secret, (string) $request->header('Authorization', ''))) {
            return $this->fail('Invalid signature.', 401);
        }

        $validated = $request->validate([
            'event' => ['required', 'array'],
            'event.type' => ['required', 'string', 'max:64'],
            'event.id' => ['nullable', 'string', 'max:191'],
            'event.app_user_id' => ['nullable', 'string', 'max:191'],
            'event.original_app_user_id' => ['nullable', 'string', 'max:191'],
            'event.product_id' => ['nullable', 'string', 'max:191'],
            'event.new_product_id' => ['nullable', 'string', 'max:191'],
            'event.period_type' => ['nullable', 'string', 'max:32'],
            'event.store' => ['nullable', 'string', 'max:32'],
            'event.environment' => ['nullable', 'string', 'max:32'],
            'event.purchased_at_ms' => ['nullable', 'integer'],
            'event.expiration_at_ms' => ['nullable', 'integer'],
            'event.event_timestamp_ms' => ['nullable', 'integer'],
            'event.grace_period_expiration_at_ms' => ['nullable', 'integer'],
            'event.cancel_reason' => ['nullable', 'string', 'max:64'],
            'event.expiration_reason' => ['nullable', 'string', 'max:64'],
            'event.transferred_from' => ['nullable', 'array'],
            'event.transferred_from.*' => ['string', 'max:191'],
            'event.transferred_to' => ['nullable', 'array'],
            'event.transferred_to.*' => ['string', 'max:191'],
        ]);

        $event = $validated['event'];
        $type = strtoupper($event['type']);

        if ($type === 'TEST') {
            return $this->ok(null, 'Test event received.');
        }

        // A transfer moves the purchase between app users: refresh both sides.
        if ($type === 'TRANSFER') {
            $ids = array_merge($event['transferred_from'] ?? [], $event['transferred_to'] ?? []);

            foreach ($ids as $id) {
                if ($user = $this->findUser($id)) {
                    $this->refreshOrExpire($user, in_array($id, $event['transferred_from'] ?? [], true));
                }
            }

            return $this->ok(null, 'Transfer processed.');
        }

        $user = $this->findUser($event['app_user_id'] ?? null)
            ?? $this->findUser($event['original_app_user_id'] ?? null);

        if (! $user) {
            // 200 so RevenueCat does not retry forever (deleted or anonymous user).
            Log::info('RevenueCat event for unknown user', [
                'type' => $type,
                'app_user_id' => $event['app_user_id'] ?? null,
            ]);

            return $this->ok(null, 'Ignored.');
        }

        if (! $this->subscriptions->refreshFromRevenueCat($user)) {
            $this->subscriptions->applyEvent($user, $event);
        }

        return $this->ok(null, 'Subscription updated.');
    }

    /** The app sets RevenueCat's app_user_id to the Saverr user id. */
    private function findUser(?string $appUserId): ?User
    {
        if (! $appUserId || ! ctype_digit($appUserId)) {
            return null;
        }

        return User::find((int) $appUserId);
    }

    private function refreshOrExpire(User $user, bool $lostPurchase): void
    {
        if ($this->subscriptions->refreshFromRevenueCat($user)) {
            return;
        }

        if ($lostPurchase) {
            $this->subscriptions->expireStoreSubscriptions($user);
        }
    }
}
