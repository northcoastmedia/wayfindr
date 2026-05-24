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
}
