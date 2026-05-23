<?php

namespace App\Support;

class RealtimeHealth
{
    /**
     * @return array{
     *     driver: string,
     *     endpoint: string,
     *     has_app_id: bool,
     *     has_app_key: bool,
     *     has_app_secret: bool,
     *     label: string,
     *     message: string,
     *     scheme: string,
     *     status: string
     * }
     */
    public function summary(): array
    {
        $driver = (string) config('broadcasting.default', 'null');
        $key = config('broadcasting.connections.reverb.key');
        $secret = config('broadcasting.connections.reverb.secret');
        $appId = config('broadcasting.connections.reverb.app_id');
        $host = config('broadcasting.connections.reverb.options.host');
        $port = config('broadcasting.connections.reverb.options.port');
        $scheme = config('broadcasting.connections.reverb.options.scheme');

        $hasAppId = $this->hasValue($appId);
        $hasAppKey = $this->hasValue($key);
        $hasAppSecret = $this->hasValue($secret);
        $hasPublicEndpoint = $this->hasValue($host) && $this->hasValue($port) && $this->hasValue($scheme);

        if ($driver !== 'reverb') {
            return [
                'driver' => $driver,
                'endpoint' => $hasPublicEndpoint ? sprintf('%s:%s', $host, $port) : 'Not configured',
                'has_app_id' => $hasAppId,
                'has_app_key' => $hasAppKey,
                'has_app_secret' => $hasAppSecret,
                'label' => 'Disabled',
                'message' => 'Set BROADCAST_CONNECTION=reverb to deliver live updates.',
                'scheme' => $this->displayValue($scheme),
                'status' => 'disabled',
            ];
        }

        if (! $hasAppId || ! $hasAppKey || ! $hasAppSecret || ! $hasPublicEndpoint) {
            return [
                'driver' => $driver,
                'endpoint' => 'Incomplete',
                'has_app_id' => $hasAppId,
                'has_app_key' => $hasAppKey,
                'has_app_secret' => $hasAppSecret,
                'label' => 'Needs setup',
                'message' => 'Add Reverb app credentials and public host settings before enabling live updates.',
                'scheme' => $this->displayValue($scheme),
                'status' => 'warning',
            ];
        }

        return [
            'driver' => $driver,
            'endpoint' => sprintf('%s:%s', $host, $port),
            'has_app_id' => true,
            'has_app_key' => true,
            'has_app_secret' => true,
            'label' => 'Ready',
            'message' => 'Reverb broadcasts are configured.',
            'scheme' => (string) $scheme,
            'status' => 'ready',
        ];
    }

    private function hasValue(mixed $value): bool
    {
        return is_string($value) ? trim($value) !== '' : $value !== null;
    }

    private function displayValue(mixed $value): string
    {
        if (! $this->hasValue($value)) {
            return 'Missing';
        }

        return (string) $value;
    }
}
