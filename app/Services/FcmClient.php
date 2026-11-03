<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging over the HTTP v1 API, with no SDK: the service
 * account JWT is signed with openssl and traded for an access token that
 * is cached until just before it expires.
 */
class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const TOKEN_CACHE_KEY = 'fcm.access_token';

    public function isConfigured(): bool
    {
        return (bool) config('fcm.enabled') && (bool) config('fcm.project_id');
    }

    /**
     * @param  array{title: string, body: ?string, data: array<string, string>}  $message
     */
    public function send(string $deviceToken, array $message): ?Response
    {
        $accessToken = $this->accessToken();

        if (! $accessToken) {
            return null;
        }

        $projectId = config('fcm.project_id');

        return Http::withToken($accessToken)
            ->timeout(10)
            ->retry(2, 200, throw: false)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $message['title'],
                        'body' => $message['body'] ?? '',
                    ],
                    'data' => $message['data'] ?? [],
                    'android' => ['priority' => 'high'],
                    'apns' => [
                        'payload' => ['aps' => ['sound' => 'default', 'badge' => 1]],
                    ],
                ],
            ]);
    }

    /** True when FCM says this token no longer belongs to a live install. */
    public function isDeadToken(Response $response): bool
    {
        return in_array(
            $response->json('error.status'),
            ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'],
            true
        );
    }

    protected function accessToken(): ?string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, 3300, function () {
            $credentials = $this->credentials();

            if (! $credentials) {
                return null;
            }

            $now = time();
            $jwt = $this->sign([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_ENDPOINT,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key']);

            if (! $jwt) {
                return null;
            }

            $response = Http::asForm()->timeout(10)->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                Log::error('FCM token exchange failed', ['body' => $response->body()]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    /** @return array{client_email: string, private_key: string}|null */
    protected function credentials(): ?array
    {
        $path = config('fcm.credentials');

        if (! $path || ! is_readable($path)) {
            Log::warning('FCM is enabled but the service account file is missing.', ['path' => $path]);

            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! isset($json['client_email'], $json['private_key'])) {
            return null;
        }

        return ['client_email' => $json['client_email'], 'private_key' => $json['private_key']];
    }

    protected function sign(array $claims, string $privateKey): ?string
    {
        $input = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            . '.' . $this->base64Url(json_encode($claims));

        $signature = '';

        if (! openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            Log::error('FCM JWT signing failed.');

            return null;
        }

        return $input . '.' . $this->base64Url($signature);
    }

    protected function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
