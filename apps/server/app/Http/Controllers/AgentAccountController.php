<?php

namespace App\Http\Controllers;

use App\Enums\AccountRole;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentAccountController extends Controller
{
    public function __invoke(Request $request): View
    {
        $agent = $request->user();

        abort_unless($agent?->account_id, 403);

        $account = $agent->account()->firstOrFail();
        $visibleSiteIds = $account->sites()
            ->visibleToAgent($agent)
            ->pluck('sites.id')
            ->map(fn (int|string $siteId): int => (int) $siteId)
            ->all();

        $agents = $account->agents()
            ->withCount([
                'assignedConversations as visible_open_conversations_count' => fn ($query) => $query
                    ->where('status', 'open')
                    ->whereIn('site_id', $visibleSiteIds),
                'assignedTickets as visible_open_tickets_count' => fn ($query) => $query
                    ->where('account_id', $account->id)
                    ->where('status', 'open')
                    ->whereIn('site_id', $visibleSiteIds),
            ])
            ->orderByRaw(
                'case account_role when ? then 0 when ? then 1 else 2 end',
                [AccountRole::Owner->value, AccountRole::Admin->value],
            )
            ->orderBy('name')
            ->orderBy('email')
            ->get();

        $visibleSites = $account->sites()
            ->visibleToAgent($agent)
            ->with(['supportAgents' => fn ($query) => $query
                ->where('users.account_id', $account->id)
                ->whereNull('users.deactivated_at')
                ->orderByRaw(
                    'case account_role when ? then 0 when ? then 1 else 2 end',
                    [AccountRole::Owner->value, AccountRole::Admin->value],
                )
                ->orderBy('name')
                ->orderBy('email')])
            ->orderBy('name')
            ->get();

        $fallbackSites = $visibleSites
            ->filter(fn ($site): bool => $site->supportAgents->isEmpty())
            ->values();

        $agentSupportScopes = $agents->mapWithKeys(fn ($accountAgent): array => [
            $accountAgent->id => [
                'explicitSites' => $visibleSites
                    ->filter(fn ($site): bool => $site->supportAgents->contains('id', $accountAgent->id))
                    ->values(),
                'fallbackSites' => $accountAgent->isDeactivated() ? collect() : $fallbackSites,
            ],
        ]);

        return view('agent.account.show', [
            'account' => $account,
            'agent' => $agent,
            'agents' => $agents,
            'agentSupportScopes' => $agentSupportScopes,
            'activeAgentCount' => $agents->reject->isDeactivated()->count(),
            'canCreateAgents' => $agent->isAdmin(),
            'canManageAgentAccess' => $agent->isAdmin(),
            'canManageRoles' => $agent->isOwner(),
            'roleLabels' => $this->roleLabels(),
            'roleOptions' => $this->roleLabels(),
            'siteCount' => $account->sites()->count(),
            'supportAssignmentCount' => $agentSupportScopes
                ->sum(fn (array $scope): int => $scope['explicitSites']->count()),
            'visibleSites' => $visibleSites,
            'visibleSiteCount' => count($visibleSiteIds),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function roleLabels(): array
    {
        return [
            AccountRole::Owner->value => 'Owner',
            AccountRole::Admin->value => 'Admin',
            AccountRole::Agent->value => 'Agent',
        ];
    }
}
