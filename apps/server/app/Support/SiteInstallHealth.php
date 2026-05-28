<?php

namespace App\Support;

use App\Models\Visitor;
use Illuminate\Support\Carbon;

class SiteInstallHealth
{
    /**
     * @return array{label: string, tone: string, detail: string, needs_attention: bool, action_label: string|null}
     */
    public static function fromVisitor(?Visitor $visitor, ?Carbon $now = null): array
    {
        $lastSeenAt = $visitor?->last_seen_at;

        if (! $lastSeenAt) {
            return [
                'label' => 'Not installed',
                'tone' => 'attention',
                'detail' => 'No check-in yet',
                'needs_attention' => true,
                'action_label' => 'Finish install',
            ];
        }

        $now ??= now();

        if ($lastSeenAt->greaterThanOrEqualTo($now->copy()->subMinutes(30))) {
            return [
                'label' => 'Live',
                'tone' => 'ready',
                'detail' => 'Seen '.$lastSeenAt->diffForHumans(),
                'needs_attention' => false,
                'action_label' => null,
            ];
        }

        return [
            'label' => 'Needs check',
            'tone' => 'manual',
            'detail' => 'Seen '.$lastSeenAt->diffForHumans(),
            'needs_attention' => true,
            'action_label' => 'Review install',
        ];
    }
}
