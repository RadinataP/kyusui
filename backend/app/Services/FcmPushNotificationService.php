<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmPushNotificationService
{
    public function send(Notification $notification): void
    {
        $projectId = config('services.fcm.project_id');
        $credentials = $this->credentials();

        if (! is_string($projectId) || $projectId === '' || $credentials === null) {
            Log::info('FCM push skipped because credentials are not configured.', [
                'notification_id' => $notification->id,
            ]);

            return;
        }

        try {
            $accessToken = $this->accessToken($credentials);
            $deviceTokens = DeviceToken::query()
                ->where('user_id', $notification->user_id)
                ->where('is_active', true)
                ->get();

            foreach ($deviceTokens as $deviceToken) {
                $response = Http::timeout(10)
                    ->withToken($accessToken)
                    ->post(
                        'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send',
                        ['message' => $this->messagePayload($notification, $deviceToken->token)],
                    );

                $this->handleResponse($response, $deviceToken, $notification);
            }
        } catch (Throwable $exception) {
            Log::error('FCM push failed without affecting business state.', [
                'notification_id' => $notification->id,
                'exception' => $exception,
            ]);
        }
    }

    /**
     * @return array{client_email: string, private_key: string}|null
     */
    private function credentials(): ?array
    {
        $credentialsPath = config('services.fcm.credentials');
        if (! is_string($credentialsPath) || $credentialsPath === '' || ! is_file($credentialsPath)) {
            return null;
        }

        $credentials = json_decode((string) file_get_contents($credentialsPath), true);

        return is_array($credentials)
            && isset($credentials['client_email'], $credentials['private_key'])
            ? $credentials
            : null;
    }

    /**
     * @param array{client_email: string, private_key: string} $credentials
     */
    private function accessToken(array $credentials): string
    {
        $issuedAt = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claim = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsignedToken = $header.'.'.$claim;
        $signature = '';
        if (openssl_sign($unsignedToken, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256) !== true) {
            throw new \RuntimeException('FCM credentials tidak dapat digunakan untuk membuat access token.');
        }
        $jwt = $unsignedToken.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ])->throw();

        $accessToken = $response->json('access_token');
        if (! is_string($accessToken) || $accessToken === '') {
            throw new \RuntimeException('FCM access token tidak tersedia.');
        }

        return $accessToken;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function messagePayload(Notification $notification, string $deviceToken): array
    {
        $data = collect($notification->data ?? [])
            ->map(function (mixed $value): string {
                return is_scalar($value)
                    ? (string) $value
                    : json_encode($value, JSON_THROW_ON_ERROR);
            })
            ->prepend((string) $notification->id, 'notification_id')
            ->prepend($notification->type, 'type')
            ->all();

        return [
            'token' => $deviceToken,
            'notification' => [
                'title' => $notification->title,
                'body' => $notification->body,
            ],
            'data' => $data,
        ];
    }

    private function handleResponse(Response $response, DeviceToken $deviceToken, Notification $notification): void
    {
        if ($response->successful()) {
            Log::info('FCM push sent.', [
                'notification_id' => $notification->id,
                'device_token_id' => $deviceToken->id,
            ]);

            return;
        }

        $errorCode = strtoupper((string) $response->json('error.status', ''));
        if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            $deviceToken->update(['is_active' => false]);
        }

        Log::warning('FCM push rejected.', [
            'notification_id' => $notification->id,
            'device_token_id' => $deviceToken->id,
            'status' => $response->status(),
            'error' => $errorCode,
        ]);
    }
}
