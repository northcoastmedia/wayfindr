<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Visitor;
use App\Support\VisitorSessionToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    public function __invoke(Request $request, VisitorSessionToken $visitorSessionToken): JsonResponse
    {
        $validated = $request->validate([
            'site_public_key' => ['required', 'string', 'max:255'],
            'anonymous_id' => ['required', 'string', 'max:255'],
            'page_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $site = Site::query()
            ->where('public_key', $validated['site_public_key'])
            ->first();

        abort_unless($site, 404, 'Site not found.');

        $visitor = Visitor::query()->updateOrCreate(
            [
                'site_id' => $site->id,
                'anonymous_id' => $validated['anonymous_id'],
            ],
            [
                'metadata' => [
                    'last_page_url' => $validated['page_url'] ?? null,
                ],
                'last_seen_at' => now(),
            ],
        );

        return response()->json([
            'data' => [
                'site' => $this->sitePayload($site),
                'visitor' => [
                    'anonymous_id' => $visitor->anonymous_id,
                    'token' => $visitorSessionToken->issue($site, $visitor),
                ],
            ],
        ], $visitor->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * @return array{name: string, domain: string|null, public_key: string, settings: array{mask_selectors: array<int, string>}}
     */
    private function sitePayload(Site $site): array
    {
        return [
            'name' => $site->name,
            'domain' => $site->domain,
            'public_key' => $site->public_key,
            'settings' => [
                'mask_selectors' => $site->settings['mask_selectors'] ?? [],
            ],
        ];
    }
}
