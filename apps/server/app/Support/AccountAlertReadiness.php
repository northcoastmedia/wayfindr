<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class AccountAlertReadiness
{
    /**
     * @param  Collection<int, User>  $agents
     * @return array{
     *     status: string,
     *     label: string,
     *     detail: string,
     *     metrics: array<int, array{label: string, value: string, tone: string}>
     * }
     */
    public function summarize(Collection $agents): array
    {
        $activeAgents = $agents->reject->isDeactivated();
        $deactivatedCount = $agents->count() - $activeAgents->count();

        $immediateEmailCount = $activeAgents
            ->filter(fn (User $agent): bool => $this->receivesImmediateEmail($agent))
            ->count();

        $digestAgents = $activeAgents
            ->filter(fn (User $agent): bool => $this->receivesDigestEmail($agent));

        $digestReadyCount = $digestAgents
            ->filter(fn (User $agent): bool => in_array($agent->alertDigestDeliveryStatus()['status'], [
                User::ALERT_DIGEST_DELIVERY_QUEUED,
                User::ALERT_DIGEST_DELIVERY_NO_ALERTS,
            ], true))
            ->count();

        $digestManualCount = $digestAgents
            ->filter(fn (User $agent): bool => $agent->alertDigestDeliveryStatus()['status'] === User::ALERT_DIGEST_DELIVERY_NOT_RUN)
            ->count();

        $attentionCount = $digestAgents
            ->filter(fn (User $agent): bool => $agent->alertDigestDeliveryStatus()['status'] === User::ALERT_DIGEST_DELIVERY_FAILED)
            ->count();

        $dashboardOnlyOrQuietCount = $activeAgents
            ->filter(fn (User $agent): bool => $agent->alertMode() === User::ALERT_MODE_QUIET || ! $agent->alertEmailEnabled())
            ->count();

        return [
            'status' => $this->status($attentionCount, $digestManualCount),
            'label' => $this->label($attentionCount, $digestManualCount),
            'detail' => $this->detail($attentionCount, $digestManualCount, $activeAgents->count()),
            'metrics' => [
                [
                    'label' => 'Active agents',
                    'value' => $activeAgents->count().' '.str('active')->plural($activeAgents->count()),
                    'tone' => 'ready',
                ],
                [
                    'label' => 'Immediate email',
                    'value' => $immediateEmailCount.' immediate '.str('email')->plural($immediateEmailCount),
                    'tone' => 'ready',
                ],
                [
                    'label' => 'Digest ready',
                    'value' => $digestReadyCount.' digest ready',
                    'tone' => 'ready',
                ],
                [
                    'label' => 'Digest baseline',
                    'value' => $digestManualCount.' digest needs baseline',
                    'tone' => $digestManualCount > 0 ? 'manual' : 'ready',
                ],
                [
                    'label' => 'Needs attention',
                    'value' => $attentionCount.' needs attention',
                    'tone' => $attentionCount > 0 ? 'attention' : 'ready',
                ],
                [
                    'label' => 'Dashboard only',
                    'value' => $dashboardOnlyOrQuietCount.' dashboard only or quiet',
                    'tone' => $dashboardOnlyOrQuietCount > 0 ? 'manual' : 'ready',
                ],
                [
                    'label' => 'Deactivated',
                    'value' => $deactivatedCount.' deactivated',
                    'tone' => $deactivatedCount > 0 ? 'manual' : 'ready',
                ],
            ],
        ];
    }

    private function receivesImmediateEmail(User $agent): bool
    {
        return $agent->alertMode() !== User::ALERT_MODE_QUIET
            && $agent->alertEmailEnabled()
            && $agent->alertCadence() === User::ALERT_CADENCE_IMMEDIATE;
    }

    private function receivesDigestEmail(User $agent): bool
    {
        return $agent->alertMode() !== User::ALERT_MODE_QUIET
            && $agent->alertEmailEnabled()
            && $agent->alertCadence() === User::ALERT_CADENCE_DIGEST;
    }

    private function status(int $attentionCount, int $digestManualCount): string
    {
        if ($attentionCount > 0) {
            return 'attention';
        }

        if ($digestManualCount > 0) {
            return 'manual';
        }

        return 'ready';
    }

    private function label(int $attentionCount, int $digestManualCount): string
    {
        if ($attentionCount > 0) {
            return $attentionCount.' '.str('agent')->plural($attentionCount).' '.($attentionCount === 1 ? 'needs' : 'need').' attention';
        }

        if ($digestManualCount > 0) {
            return $digestManualCount.' digest '.str('baseline')->plural($digestManualCount).' needed';
        }

        return 'Alert delivery looks ready';
    }

    private function detail(int $attentionCount, int $digestManualCount, int $activeCount): string
    {
        if ($attentionCount > 0) {
            return 'Digest delivery needs a mail/provider check. Raw provider errors stay in logs, not this account page.';
        }

        if ($digestManualCount > 0) {
            return 'Run the alert digest command once after scheduler setup so digest-enabled agents have a recorded baseline.';
        }

        if ($activeCount === 0) {
            return 'No active agents can receive alerts yet.';
        }

        return 'Active agents have a readable notification path for their current preferences.';
    }
}
