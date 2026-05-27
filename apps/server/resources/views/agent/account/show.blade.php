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

            @if (session('status'))
                <p class="status-message">{{ session('status') }}</p>
            @endif

            @if (session('created_agent_email') && session('created_agent_password'))
                <section class="section" aria-labelledby="temporary-password-heading">
                    <div class="section-header">
                        <h2 id="temporary-password-heading">Temporary password</h2>
                        <span class="lede">{{ session('created_agent_email') }}</span>
                    </div>
                    <div class="notice-copy">
                        <p>Share this password securely. It is shown once and should be changed by the agent after sign-in.</p>
                    </div>
                    <pre class="code-block"><code>{{ session('created_agent_password') }}</code></pre>
                </section>
            @endif

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
                    <span class="lede">{{ $canManageRoles ? 'Owner controls enabled' : 'Read-only for your role' }}</span>
                </div>
                <div class="notice-copy">
                    <p>Account roles describe authority. Site access still decides which support queues an agent can work.</p>
                    <p>Role changes are limited to account owners. Owners cannot change their own role here, and every role change is audited.</p>
                </div>
            </section>

            @if ($canCreateAgents)
                <section class="section" aria-labelledby="add-agent-heading">
                    <div class="section-header">
                        <h2 id="add-agent-heading">Add agent</h2>
                        <span class="lede">New agents start with the Agent role</span>
                    </div>
                    <form class="section-form" method="POST" action="{{ route('dashboard.account.agents.store') }}">
                        @csrf
                        <div class="field">
                            <label for="agent-name">Name</label>
                            <input id="agent-name" name="name" value="{{ old('name') }}" autocomplete="name" required>
                            @error('name')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="field">
                            <label for="agent-email">Email</label>
                            <input id="agent-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                            @error('email')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <p class="field-help">A temporary password will be generated. Site access follows the current account-wide fallback until you scope agents on each site.</p>
                        <button class="button" type="submit">Create agent</button>
                    </form>
                </section>
            @endif

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
                                @if ($canManageRoles)
                                    <th scope="col">Manage role</th>
                                @endif
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
                                    @if ($canManageRoles)
                                        <td>
                                            @if ($accountAgent->is($agent))
                                                <span class="lede">Current user</span>
                                            @else
                                                <form class="compact-form" method="POST" action="{{ route('dashboard.account.agents.role.update', $accountAgent) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <label class="sr-only" for="account-role-{{ $accountAgent->id }}">Manage role for {{ $accountAgent->name }}</label>
                                                    <select id="account-role-{{ $accountAgent->id }}" name="account_role">
                                                        @foreach ($roleOptions as $roleValue => $roleLabel)
                                                            <option value="{{ $roleValue }}" @selected($accountAgent->account_role?->value === $roleValue)>{{ $roleLabel }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button class="button secondary" type="submit">Save role</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
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
