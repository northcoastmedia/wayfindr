<?php

namespace App\Http\Controllers;

use App\Enums\AccountRole;
use App\Models\Account;
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
                'supportedSites as explicit_site_access_count' => fn ($query) => $query
                    ->where('sites.account_id', $account->id)
                    ->whereIn('sites.id', $visibleSiteIds),
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

        return view('agent.account.show', [
            'account' => $account,
            'agent' => $agent,
            'agents' => $agents,
            'roleLabels' => $this->roleLabels(),
            'siteCount' => $account->sites()->count(),
            'supportAssignmentCount' => $agents->sum('explicit_site_access_count'),
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
