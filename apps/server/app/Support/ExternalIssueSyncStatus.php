<?php

namespace App\Support;

final class ExternalIssueSyncStatus
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'linked' => 'Linked',
            'sync_pending' => 'Sync pending',
            'sync_failed' => 'Sync failed',
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(self::options());
    }

    public static function label(?string $status): string
    {
        return self::options()[$status ?? ''] ?? 'Unknown';
    }
}
