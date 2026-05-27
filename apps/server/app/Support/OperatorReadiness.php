<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

class OperatorReadiness
{
    public function __construct(
        private readonly RealtimeHealth $realtimeHealth,
    ) {}

    /**
     * @return array{
     *     attention_count: int,
     *     checks: array<int, array{action: string, detail: string, key: string, label: string, status: string, status_label: string, summary: string}>,
     *     label: string,
     *     manual_count: int,
     *     ready_count: int
     * }
     */
    public function summary(): array
    {
        $checks = [
            $this->applicationKey(),
            $this->databaseConnection(),
            $this->queueWorker(),
            $this->realtimeBroadcasting(),
            $this->storagePaths(),
            $this->scheduler(),
        ];

        $attentionCount = count(array_filter($checks, fn (array $check): bool => $check['status'] === 'attention'));
        $manualCount = count(array_filter($checks, fn (array $check): bool => $check['status'] === 'manual'));
        $readyCount = count(array_filter($checks, fn (array $check): bool => $check['status'] === 'ready'));

        return [
            'attention_count' => $attentionCount,
            'checks' => $checks,
            'label' => $attentionCount > 0 ? 'Needs attention' : 'Ready',
            'manual_count' => $manualCount,
            'ready_count' => $readyCount,
        ];
    }

    /**
     * @return array{action: string, detail: string, key: string, label: string, status: string, status_label: string, summary: string}
     */
    private function applicationKey(): array
    {
        if ($this->hasValue(config('app.key'))) {
            return $this->check(
                key: 'application_key',
                label: 'Application key',
                status: 'ready',
                summary: 'APP_KEY is set.',
                detail: 'Encrypted cookies, sessions, and signed data have an application key available.',
                action: 'Keep this value secret and stable between deploys.'
            );
        }

        return $this->check(
            key: 'application_key',
            label: 'Application key',
            status: 'attention',
            summary: 'APP_KEY is missing.',
            detail: 'Laravel cannot safely encrypt sessions, cookies, or signed data without an application key.',
            action: 'Run php artisan key:generate and save the generated APP_KEY in the environment.'
        );
    }

    /**
     * @return array{action: string, detail: string, key: string, label: string, status: string, status_label: string, summary: string}
     */
    private function databaseConnection(): array
    {
        $connection = (string) config('database.default', 'unknown');

        try {
            DB::connection()->select('select 1');
        } catch (Throwable $exception) {
            return $this->check(
                key: 'database_connection',
                label: 'Database connection',
                status: 'attention',
                summary: sprintf('The %s connection could not be reached.', $connection),
                detail: $exception->getMessage(),
                action: 'Review DB_CONNECTION, DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD.'
            );
        }

        return $this->check(
            key: 'database_connection',
            label: 'Database connection',
            status: 'ready',
            summary: sprintf('The %s connection responded.', $connection),
            detail: 'Wayfindr can reach the configured database.',
            action: 'Keep migrations in the deploy script so schema changes land with releases.'
        );
    }

    /**
     * @return array{action: string, detail: string, key: string, label: string, status: string, status_label: string, summary: string}
     */
    private function queueWorker(): array
    {
        $connection = (string) config('queue.default', 'sync');

        if (in_array($connection, ['null', 'sync'], true)) {
            return $this->check(
                key: 'queue_worker',
                label: 'Queue worker',
                status: 'attention',
                summary: sprintf('QUEUE_CONNECTION is %s.', $connection),
                detail: 'Synchronous queues are useful locally, but production installs should run durable background workers.',
                action: 'Use database or redis queues and run php artisan queue:work under Supervisor or your process manager.'
            );
        }

        return $this->check(
            key: 'queue_worker',
            label: 'Queue worker',
            status: 'ready',
            summary: sprintf('QUEUE_CONNECTION is %s.', $connection),
            detail: 'The queue driver is configured for background work.',
            action: 'Make sure a queue worker is running in Forge, systemd, Supervisor, or your deployment platform.'
        );
    }

    /**
     * @return array{action: string, detail: string, key: string, label: string, status: string, status_label: string, summary: string}
     */
    private function realtimeBroadcasting(): array
    {
        $realtime = $this->realtimeHealth->summary();
        $status = $realtime['status'] === 'ready' ? 'ready' : 'attention';

        return $this->check(
            key: 'realtime_broadcasting',
            label: 'Realtime broadcasting',
            status: $status,
            summary: $realtime['label'],
            detail: $realtime['message'],
            action: $status === 'ready'
                ? 'Keep php artisan reverb:restart in the deploy script so long-running Reverb workers refresh cleanly.'
                : 'Set BROADCAST_CONNECTION=reverb plus REVERB_APP_ID, REVERB_APP_KEY, REVERB_APP_SECRET, REVERB_HOST, REVERB_PORT, and REVERB_SCHEME.'
        );
    }

    /**
     * @return array{action: string, detail: string, key: string, label: string, status: string, status_label: string, summary: string}
     */
    private function storagePaths(): array
    {
        $paths = [
            storage_path('framework'),
            storage_path('logs'),
        ];
        $unwritablePaths = array_filter($paths, fn (string $path): bool => ! is_dir($path) || ! is_writable($path));

        if ($unwritablePaths !== []) {
            return $this->check(
                key: 'storage_paths',
                label: 'Storage paths',
                status: 'attention',
                summary: 'One or more storage paths are not writable.',
                detail: implode(', ', $unwritablePaths),
                action: 'Make storage/framework and storage/logs writable by the PHP user.'
            );
        }

        return $this->check(
            key: 'storage_paths',
            label: 'Storage paths',
            status: 'ready',
            summary: 'Laravel storage paths are writable.',
            detail: 'Cache, compiled views, sessions, and logs can be written by the application.',
            action: 'Keep storage shared between zero-downtime releases.'
        );
    }

    /**
     * @return array{action: string, detail: string, key: string, label: string, status: string, status_label: string, summary: string}
     */
    private function scheduler(): array
    {
        return $this->check(
            key: 'scheduler',
            label: 'Scheduler',
            status: 'manual',
            summary: 'Confirm the Laravel scheduler is running once per minute.',
            detail: 'Wayfindr cannot safely prove cron or external scheduler setup from inside the request.',
            action: 'Configure * * * * * php artisan schedule:run or the equivalent scheduled job in your host.'
        );
    }

    /**
     * @return array{action: string, detail: string, key: string, label: string, status: string, status_label: string, summary: string}
     */
    private function check(string $key, string $label, string $status, string $summary, string $detail, string $action): array
    {
        return [
            'action' => $action,
            'detail' => $detail,
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'status_label' => match ($status) {
                'ready' => 'Ready',
                'manual' => 'Manual check',
                default => 'Needs attention',
            },
            'summary' => $summary,
        ];
    }

    private function hasValue(mixed $value): bool
    {
        return is_string($value) ? trim($value) !== '' : $value !== null;
    }
}
