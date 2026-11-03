<?php

namespace App\Jobs;

use App\Models\UserDevice;
use App\Services\FcmClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push delivery runs off the request so a slow Firebase call never holds
 * up an API response. The in-app copy is already saved by then.
 */
class SendFcmMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array{title: string, body: ?string, data: array<string, string>}  $message
     */
    public function __construct(
        public int $userId,
        public array $message,
    ) {
    }

    public function handle(FcmClient $client): void
    {
        if (! $client->isConfigured()) {
            return;
        }

        $devices = UserDevice::where('user_id', $this->userId)->get(['id', 'push_token']);

        foreach ($devices as $device) {
            $response = $client->send($device->push_token, $this->message);

            if (! $response || $response->successful()) {
                continue;
            }

            // A token the device no longer owns is dead weight; drop it.
            if ($client->isDeadToken($response)) {
                $device->delete();
                continue;
            }

            Log::warning('FCM delivery failed', [
                'device_id' => $device->id,
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);
        }
    }
}
