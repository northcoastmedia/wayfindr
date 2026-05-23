<?php

namespace App\Support;

use App\Models\Conversation;

class CobrowseConsentState
{
    /**
     * @return array{label: string, message: string, status: string}
     */
    public function forConversation(Conversation $conversation): array
    {
        $session = $conversation->cobrowseSessions()
            ->latest('id')
            ->first();

        if (! $session) {
            return [
                'label' => 'Unavailable',
                'message' => 'Visitor has not granted cobrowse consent.',
                'status' => 'unavailable',
            ];
        }

        return match ($session->status) {
            'requested' => [
                'label' => 'Pending consent',
                'message' => 'Waiting for visitor consent before cobrowsing can start.',
                'status' => 'pending',
            ],
            'granted' => [
                'label' => 'Granted',
                'message' => 'Visitor granted cobrowse consent.',
                'status' => 'granted',
            ],
            'revoked' => [
                'label' => 'Revoked',
                'message' => 'Visitor revoked cobrowse consent.',
                'status' => 'revoked',
            ],
            default => [
                'label' => 'Unavailable',
                'message' => 'Visitor has not granted cobrowse consent.',
                'status' => 'unavailable',
            ],
        };
    }
}
