<?php

namespace App\Support;

final class ExternalIssueProvider
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'github' => 'GitHub',
            'gitlab' => 'GitLab',
            'bitbucket' => 'Bitbucket',
            'jira' => 'Jira',
            'other' => 'Other',
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(self::options());
    }

    public static function label(?string $provider): string
    {
        return self::options()[$provider ?? ''] ?? 'External tracker';
    }
}
