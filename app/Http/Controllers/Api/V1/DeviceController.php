<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterDeviceRequest;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FCM device registration. Tokens are matched on a hash so the unique
 * index stays within MySQL's key length.
 */
class DeviceController extends Controller
{
    use ApiResponses;

    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $device = $request->user()->devices()->updateOrCreate(
            ['push_token_hash' => hash('sha256', $data['push_token'])],
            [
                'push_token' => $data['push_token'],
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return $this->ok(['id' => $device->id], 'Device registered for notifications.');
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_token' => ['required', 'string', 'min:10', 'max:512'],
        ]);

        $request->user()->devices()
            ->where('push_token_hash', hash('sha256', $validated['push_token']))
            ->delete();

        return $this->ok(null, 'Device unregistered.');
    }
}
