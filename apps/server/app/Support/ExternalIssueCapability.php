<?php

namespace App\Support;

final class ExternalIssueCapability
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'create_issue' => 'Create issues',
            'add_comment' => 'Add comments',
            'sync_status' => 'Sync status',
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(self::options());
    }

    public static function label(string $capability): string
    {
        return self::options()[$capability] ?? ucfirst(str_replace('_', ' ', $capability));
    }

    /**
     * @param  array<int, string>|array<string, bool>|null  $capabilities
     * @return array<string, bool>
     */
    public static function flags(?array $capabilities): array
    {
        $selected = collect($capabilities ?? [])
            ->filter()
            ->mapWithKeys(fn (bool|string $enabled, int|string $capability): array => is_string($capability)
                ? [(string) $capability => (bool) $enabled]
                : [(string) $enabled => true])
            ->all();

        return collect(self::values())
            ->mapWithKeys(fn (string $capability): array => [$capability => (bool) ($selected[$capability] ?? false)])
            ->all();
    }

    /**
     * @param  array<string, bool>|null  $capabilities
     * @return list<string>
     */
    public static function activeLabels(?array $capabilities): array
    {
        return collect(self::flags($capabilities))
            ->filter()
            ->keys()
            ->map(fn (string $capability): string => self::label($capability))
            ->values()
            ->all();
    }
}
