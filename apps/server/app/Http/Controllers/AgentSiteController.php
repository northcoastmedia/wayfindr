<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentSiteController extends Controller
{
    public function show(Request $request, Site $site): View
    {
        $this->authorizeSite($request, $site);

        return view('agent.sites.show', [
            'account' => $request->user()->account()->firstOrFail(),
            'agent' => $request->user(),
            'dataResponsibility' => config('wayfindr.data_responsibility'),
            'maskSelectors' => $this->maskSelectors($site),
            'site' => $site,
            'widgetInstallSnippet' => $this->widgetInstallSnippet($site),
        ]);
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->authorizeSite($request, $site);

        $validated = $request->validate([
            'mask_selectors' => ['nullable', 'string', 'max:4000'],
        ]);

        $settings = $site->settings ?? [];
        $settings['mask_selectors'] = $this->parseMaskSelectors($validated['mask_selectors'] ?? '');

        $site->forceFill(['settings' => $settings])->save();

        return redirect()
            ->route('dashboard.sites.show', $site)
            ->with('status', 'Site privacy settings saved.');
    }

    private function authorizeSite(Request $request, Site $site): void
    {
        $accountId = $request->user()->account_id;

        abort_unless($accountId && (int) $site->account_id === (int) $accountId, 404);
    }

    /**
     * @return array<int, string>
     */
    private function maskSelectors(Site $site): array
    {
        $selectors = $site->settings['mask_selectors'] ?? [];

        return is_array($selectors) ? array_values(array_filter($selectors, 'is_string')) : [];
    }

    /**
     * @return array<int, string>
     */
    private function parseMaskSelectors(string $value): array
    {
        $selectors = preg_split('/\R/', $value) ?: [];
        $selectors = array_map(fn (string $selector): string => trim($selector), $selectors);
        $selectors = array_filter($selectors, fn (string $selector): bool => $selector !== '');
        $selectors = array_map(fn (string $selector): string => mb_substr($selector, 0, 255), $selectors);

        return array_values(array_unique($selectors));
    }

    private function widgetInstallSnippet(Site $site): string
    {
        $baseUrl = $this->widgetBaseUrl();
        $attributes = [
            'src' => "{$baseUrl}/widget.js",
            'data-wayfindr-api-base-url' => $baseUrl,
            'data-wayfindr-site-key' => $site->public_key,
        ];

        $reverb = $this->publicReverbConfig();
        $lines = [];

        if ($reverb !== null) {
            $lines[] = '<script src="https://js.pusher.com/8.3.0/pusher.min.js"></script>';
            $attributes = [
                ...$attributes,
                'data-wayfindr-reverb-app-key' => $reverb['app_key'],
                'data-wayfindr-reverb-host' => $reverb['host'],
                'data-wayfindr-reverb-port' => $reverb['port'],
                'data-wayfindr-reverb-scheme' => $reverb['scheme'],
            ];
        }

        $lines[] = '<script';

        foreach ($attributes as $name => $value) {
            $lines[] = sprintf('  %s="%s"', $name, $this->attribute($value));
        }

        $lines[] = '></script>';

        return implode(PHP_EOL, $lines);
    }

    private function widgetBaseUrl(): string
    {
        return rtrim((string) config('app.url', url('/')), '/');
    }

    /**
     * @return array{app_key: string, host: string, port: string, scheme: string}|null
     */
    private function publicReverbConfig(): ?array
    {
        if ((string) config('broadcasting.default') !== 'reverb') {
            return null;
        }

        $appKey = config('broadcasting.connections.reverb.key');
        $host = config('broadcasting.connections.reverb.options.host');
        $port = config('broadcasting.connections.reverb.options.port');
        $scheme = config('broadcasting.connections.reverb.options.scheme');

        if (! $this->hasConfigValue($appKey) || ! $this->hasConfigValue($host) || ! $this->hasConfigValue($port) || ! $this->hasConfigValue($scheme)) {
            return null;
        }

        return [
            'app_key' => (string) $appKey,
            'host' => (string) $host,
            'port' => (string) $port,
            'scheme' => (string) $scheme,
        ];
    }

    private function hasConfigValue(mixed $value): bool
    {
        return is_string($value) ? trim($value) !== '' : $value !== null;
    }

    private function attribute(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}
