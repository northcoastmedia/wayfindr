<?php

namespace App\Support;

use App\Models\Conversation;

class CobrowseConsentState
{
    public function __construct(private readonly CobrowseReplayPreview $replayPreview) {}

    /**
     * @return array{label: string, message: string, status: string, telemetry: array<string, string>|null, page_state: array<string, string>|null, snapshot: array<string, string>|null, mutation_stream: array<string, string>|null, replay_preview: array<string, string>|null}
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
                'telemetry' => null,
                'page_state' => null,
                'snapshot' => null,
                'mutation_stream' => null,
                'replay_preview' => null,
            ];
        }

        $state = match ($session->status) {
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
            'ended' => [
                'label' => 'Ended',
                'message' => 'Cobrowse session ended.',
                'status' => 'ended',
            ],
            default => [
                'label' => 'Unavailable',
                'message' => 'Visitor has not granted cobrowse consent.',
                'status' => 'unavailable',
            ],
        };

        $state['telemetry'] = $this->formatTelemetry($session->metadata['telemetry'] ?? null);
        $state['page_state'] = $this->formatPageState($session->metadata['page_state'] ?? null);
        $state['snapshot'] = $this->formatSnapshot($session->metadata['snapshot'] ?? null);
        $state['mutation_stream'] = $this->formatMutationStream($session->metadata['mutations'] ?? null);
        $state['replay_preview'] = $this->replayPreview->fromMetadata($session->metadata ?? []);

        return $state;
    }

    /**
     * @return array<string, string>|null
     */
    private function formatTelemetry(mixed $telemetry): ?array
    {
        if (! is_array($telemetry)) {
            return null;
        }

        return [
            'rtt' => $this->formatMilliseconds($telemetry['rtt_ms'] ?? null),
            'max_rtt' => $this->formatMilliseconds($telemetry['max_rtt_ms'] ?? null),
            'payload' => $this->formatBytes($telemetry['payload_bytes'] ?? null),
            'max_payload' => $this->formatBytes($telemetry['max_payload_bytes'] ?? null),
            'dropped_batches' => number_format((int) ($telemetry['dropped_batches'] ?? 0)),
            'reconnects' => number_format((int) ($telemetry['reconnects'] ?? 0)),
            'samples' => number_format((int) ($telemetry['samples'] ?? 0)),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function formatPageState(mixed $pageState): ?array
    {
        if (! is_array($pageState)) {
            return null;
        }

        return [
            'title' => filled($pageState['title'] ?? null) ? (string) $pageState['title'] : 'Untitled page',
            'page_url' => filled($pageState['page_url'] ?? null) ? (string) $pageState['page_url'] : 'Not reported',
            'viewport' => $this->formatDimensions($pageState['viewport_width'] ?? null, $pageState['viewport_height'] ?? null),
            'scroll' => $this->formatCoordinates($pageState['scroll_x'] ?? null, $pageState['scroll_y'] ?? null),
            'visibility_state' => filled($pageState['visibility_state'] ?? null) ? (string) $pageState['visibility_state'] : 'Not reported',
            'focus' => ($pageState['focused'] ?? false) ? 'Focused' : 'Not focused',
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function formatSnapshot(mixed $snapshot): ?array
    {
        if (! is_array($snapshot)) {
            return null;
        }

        return [
            'title' => filled($snapshot['title'] ?? null) ? (string) $snapshot['title'] : 'Untitled page',
            'page_url' => filled($snapshot['page_url'] ?? null) ? (string) $snapshot['page_url'] : 'Not reported',
            'node_count' => number_format((int) ($snapshot['node_count'] ?? 0)).' nodes',
            'masked_count' => number_format((int) ($snapshot['masked_count'] ?? 0)).' masked',
            'text' => filled($snapshot['text'] ?? null) ? (string) $snapshot['text'] : 'No text preview reported.',
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function formatMutationStream(mixed $mutations): ?array
    {
        if (! is_array($mutations)) {
            return null;
        }

        return [
            'batch_count' => number_format((int) ($mutations['batch_count'] ?? 0)).' batches',
            'mutation_count' => number_format((int) ($mutations['mutation_count'] ?? 0)).' mutations',
            'dropped_count' => number_format((int) ($mutations['dropped_count'] ?? 0)).' dropped',
            'skipped_count' => number_format((int) ($mutations['skipped_count'] ?? 0)).' skipped',
            'last_sequence' => 'Sequence '.number_format((int) ($mutations['last_sequence'] ?? 0)),
            'last_page_url' => filled($mutations['last_page_url'] ?? null) ? (string) $mutations['last_page_url'] : 'Not reported',
        ];
    }

    private function formatDimensions(mixed $width, mixed $height): string
    {
        if (! is_numeric($width) || ! is_numeric($height)) {
            return 'Not reported';
        }

        return number_format((int) $width).' x '.number_format((int) $height);
    }

    private function formatCoordinates(mixed $x, mixed $y): string
    {
        if (! is_numeric($x) || ! is_numeric($y)) {
            return 'Not reported';
        }

        return number_format((int) $x).', '.number_format((int) $y);
    }

    private function formatMilliseconds(mixed $value): string
    {
        if (! is_numeric($value)) {
            return 'Not reported';
        }

        return number_format((int) $value).' ms';
    }

    private function formatBytes(mixed $value): string
    {
        if (! is_numeric($value)) {
            return 'Not reported';
        }

        return number_format((int) $value).' bytes';
    }
}
