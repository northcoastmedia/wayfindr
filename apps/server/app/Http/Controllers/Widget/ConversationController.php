<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Site;
use App\Support\VisitorSessionToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function store(Request $request, VisitorSessionToken $visitorSessionToken): JsonResponse
    {
        $validated = $request->validate([
            'site_public_key' => ['required', 'string', 'max:255'],
            'anonymous_id' => ['required', 'string', 'max:255'],
            'visitor_token' => ['nullable', 'string', 'max:4096'],
            'subject' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $site = Site::query()
            ->where('public_key', $validated['site_public_key'])
            ->first();

        abort_unless($site, 404, 'Site not found.');

        $visitor = $visitorSessionToken->visitorFromRequest($request, $site, $validated['anonymous_id']);

        $visitor->forceFill([
            'metadata' => [
                'last_page_url' => $validated['page_url'] ?? null,
            ],
            'last_seen_at' => now(),
        ])->save();

        $conversation = Conversation::query()->create([
            'site_id' => $site->id,
            'visitor_id' => $visitor->id,
            'support_code' => $this->generateSupportCode(),
            'status' => 'open',
            'subject' => $validated['subject'] ?? null,
            'metadata' => [
                'started_page_url' => $validated['page_url'] ?? null,
            ],
        ]);

        return response()->json([
            'data' => [
                'support_code' => $conversation->support_code,
                'status' => $conversation->status,
                'subject' => $conversation->subject,
                'visitor' => [
                    'anonymous_id' => $visitor->anonymous_id,
                ],
            ],
        ], 201);
    }

    private function generateSupportCode(): string
    {
        do {
            $supportCode = 'WF-'.Str::upper(Str::random(8));
        } while (Conversation::query()->where('support_code', $supportCode)->exists());

        return $supportCode;
    }
}
