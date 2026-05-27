<x-layouts.app title="Account" :agent="$agent" :account="$account">
            <div class="section-header">
                <div>
                    <h1>Account</h1>
                    <p class="lede">Your role, team roster, and visible support scope.</p>
                </div>
                <div class="section-actions">
                    <span class="lede">{{ $agents->count() }} {{ \Illuminate\Support\Str::plural('agent', $agents->count()) }}</span>
                </div>
            </div>

            <section class="section" aria-labelledby="account-context-heading">
                <div class="section-header">
                    <h2 id="account-context-heading">{{ $account->name }}</h2>
                    <span class="lede">Account boundary</span>
                </div>
                <div class="meta-grid">
                    <div class="meta-item">
                        <span class="meta-label">Your role</span>
                        <span class="meta-value">{{ $roleLabels[$agent->account_role?->value] ?? 'Agent' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Sites</span>
                        <span class="meta-value">{{ $siteCount }} {{ \Illuminate\Support\Str::plural('site', $siteCount) }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Visible to you</span>
                        <span class="meta-value">{{ $visibleSiteCount }} {{ \Illuminate\Support\Str::plural('site', $visibleSiteCount) }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Support assignments</span>
                        <span class="meta-value">{{ $supportAssignmentCount }} {{ \Illuminate\Support\Str::plural('support assignment', $supportAssignmentCount) }}</span>
                    </div>
                </div>
            </section>

            <section class="section" aria-labelledby="role-boundary-heading">
                <div class="section-header">
                    <h2 id="role-boundary-heading">Role boundary</h2>
                    <span class="lede">Read-only foundation</span>
                </div>
                <div class="notice-copy">
                    <p>Account roles describe authority. Site access still decides which support queues an agent can work.</p>
                    <p>Role management UI is not implemented yet. Owners can use the server-side role action path while the account controls mature.</p>
                </div>
            </section>

            <section class="section" aria-labelledby="agents-heading">
                <div class="section-header">
                    <h2 id="agents-heading">Agents</h2>
                    <span class="lede">{{ $agents->count() }} total</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Agent</th>
                                <th scope="col">Role</th>
                                <th scope="col">Site access</th>
                                <th scope="col">Open conversations</th>
                                <th scope="col">Open tickets</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($agents as $accountAgent)
                                <tr>
                                    <td>
                                        <strong>{{ $accountAgent->name }}</strong>
                                        <span class="lede">{{ $accountAgent->email }}</span>
                                    </td>
                                    <td>{{ $roleLabels[$accountAgent->account_role?->value] ?? 'Agent' }}</td>
                                    <td>{{ $accountAgent->explicit_site_access_count }} {{ \Illuminate\Support\Str::plural('support assignment', $accountAgent->explicit_site_access_count) }}</td>
                                    <td>{{ $accountAgent->visible_open_conversations_count }} {{ \Illuminate\Support\Str::plural('open conversation', $accountAgent->visible_open_conversations_count) }}</td>
                                    <td>{{ $accountAgent->visible_open_tickets_count }} {{ \Illuminate\Support\Str::plural('open ticket', $accountAgent->visible_open_tickets_count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
</x-layouts.app>
