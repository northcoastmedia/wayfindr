<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Site;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationMessageController extends Controller
{
    public function store(Request $request, string $supportCode): JsonResponse
    {
        $validated = $request->validate([
            'site_public_key' => ['required', 'string', 'max:255'],
            'anonymous_id' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $site = Site::query()
            ->where('public_key', $validated['site_public_key'])
            ->first();

        abort_unless($site, 404, 'Site not found.');

        $visitor = Visitor::query()
            ->where('site_id', $site->id)
            ->where('anonymous_id', $validated['anonymous_id'])
            ->first();

        abort_unless($visitor, 404, 'Visitor not found.');

        $conversation = Conversation::query()
            ->where('support_code', $supportCode)
            ->where('site_id', $site->id)
            ->where('visitor_id', $visitor->id)
            ->first();

        abort_unless($conversation, 404, 'Conversation not found.');

        $message = $conversation->messages()->create([
            'sender_type' => Visitor::class,
            'sender_id' => $visitor->id,
            'type' => 'text',
            'body' => $validated['body'],
            'metadata' => [],
        ]);

        $conversation->forceFill([
            'last_message_at' => $message->created_at,
        ])->save();

        return response()->json([
            'data' => [
                'conversation' => [
                    'support_code' => $conversation->support_code,
                ],
                'message' => [
                    'type' => $message->type,
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toJSON(),
                ],
            ],
        ], 201);
    }
}
