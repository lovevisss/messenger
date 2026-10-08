<?php

namespace App\Services\Channels;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PortalTokenProvider
{
    public function token(bool $refresh = false): string
    {
        $settings = config('notifications.portal');
        if (filled($settings['poa_token'])) {
            return $settings['poa_token'];
        }
        if (blank($settings['base_url']) || blank($settings['client_id']) || blank($settings['client_secret'])) {
            throw new RuntimeException('门户 POA 凭据尚未配置');
        }

        $cacheKey = 'portal-poa-token-'.hash('sha256', $settings['base_url'].$settings['client_id'].$settings['scope']);
        if ($refresh) {
            Cache::forget($cacheKey);
        }
        $token = Cache::get($cacheKey);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $url = $settings['token_url'] ?: rtrim($settings['base_url'], '/').'/oauth2/token';
        $response = Http::asForm()->timeout(15)->post($url, [
            'grant_type' => 'client_credentials', 'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'], 'scope' => $settings['scope'],
        ]);
        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || blank($body['access_token'] ?? null)) {
            throw new RuntimeException('获取门户 POA token 失败');
        }

        $seconds = max(1, (int) floor((int) ($body['expires_in'] ?? 3600) * 0.8));
        Cache::put($cacheKey, $body['access_token'], $seconds);

        return $body['access_token'];
    }
}
