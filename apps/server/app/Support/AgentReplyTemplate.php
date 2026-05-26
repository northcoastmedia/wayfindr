<?php

namespace App\Support;

final class AgentReplyTemplate
{
    /**
     * @return array<string, array{label: string, body: string}>
     */
    public static function options(): array
    {
        return [
            'looking_into_it' => [
                'label' => 'Looking into it',
                'body' => 'Thanks for the update. I am looking into this now and will follow up shortly.',
            ],
            'need_more_detail' => [
                'label' => 'Need more detail',
                'body' => 'Could you share a little more detail about what you expected to happen and what happened instead?',
            ],
            'confirm_resolution' => [
                'label' => 'Confirm resolution',
                'body' => 'Thanks for your patience. I believe this is resolved now, but I am happy to keep digging if anything still looks off.',
            ],
            'ticket_follow_up' => [
                'label' => 'Ticket follow-up',
                'body' => 'I turned this into a ticket so we can track the follow-up without losing the context from this conversation.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(self::options());
    }

    public static function body(?string $template): ?string
    {
        if (! $template) {
            return null;
        }

        return self::options()[$template]['body'] ?? null;
    }
}
