<x-layouts.app title="Site Privacy Settings">
    <div class="shell">
        <header class="topbar">
            <div class="topbar-inner">
                <div>
                    <div class="brand">Wayfindr</div>
                    <div class="lede">{{ $agent->name }} - {{ $account->name }}</div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="button secondary" type="submit">Sign out</button>
                </form>
            </div>
        </header>

        <main class="page">
            <a class="text-link" href="{{ route('dashboard') }}">Back to dashboard</a>
            <h1>{{ $site->name }}</h1>
            <p class="lede">Privacy settings for {{ $site->domain ?? 'an unconfigured domain' }}</p>

            @if (session('status'))
                <p class="status-message">{{ session('status') }}</p>
            @endif

            @php
                $latestVisitor = $site->latestVisitor;
                $lastPageUrl = data_get($latestVisitor?->metadata, 'last_page_url');
                $selectedSupportAgentIds = collect(old('support_agent_ids', $supportAgentIds))
                    ->map(fn ($id) => (int) $id)
                    ->all();
            @endphp

            <section class="section" aria-labelledby="site-context-heading">
                <div class="section-header">
                    <h2 id="site-context-heading">Site</h2>
                    <span class="lede">Widget install target</span>
                </div>

                <div class="meta-grid">
                    <div class="meta-item">
                        <span class="meta-label">Name</span>
                        <span class="meta-value">{{ $site->name }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Domain</span>
                        <span class="meta-value">{{ $site->domain ?? 'Not set' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Public key</span>
                        <span class="meta-value">{{ $site->public_key }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Public config</span>
                        <span class="meta-value">Mask selectors only</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Latest check-in</span>
                        <span class="meta-value">
                            @if ($latestVisitor?->last_seen_at)
                                Seen {{ $latestVisitor->last_seen_at->diffForHumans() }}
                            @else
                                Not seen yet
                            @endif
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Last page</span>
                        <span class="meta-value">{{ $lastPageUrl ?: 'Not reported' }}</span>
                    </div>
                </div>
            </section>

            <section class="section" aria-labelledby="support-access-heading">
                <div class="section-header">
                    <h2 id="support-access-heading">Support access</h2>
                    <span class="lede">
                        @if ($siteHasExplicitSupportAgents)
                            {{ count($supportAgentIds) }} assigned
                        @else
                            Account-wide fallback
                        @endif
                    </span>
                </div>

                @if (! $siteHasExplicitSupportAgents)
                    <div class="notice-copy">
                        <p>No support agents are assigned yet, so all account agents can support this site. Saving assignments will switch this site to explicit access.</p>
                    </div>
                @endif

                @if ($canManageSiteAccess)
                    <form class="section-form" method="POST" action="{{ route('dashboard.sites.support-agents.update', $site) }}">
                        @csrf
                        @method('PUT')

                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">Assigned</th>
                                        <th scope="col">Name</th>
                                        <th scope="col">Email</th>
                                        <th scope="col">Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($accountAgents as $accountAgent)
                                        <tr>
                                            <td>
                                                <input
                                                    id="support_agent_{{ $accountAgent->id }}"
                                                    name="support_agent_ids[]"
                                                    type="checkbox"
                                                    value="{{ $accountAgent->id }}"
                                                    @checked(in_array($accountAgent->id, $selectedSupportAgentIds, true))
                                                >
                                            </td>
                                            <td>
                                                <label for="support_agent_{{ $accountAgent->id }}">{{ $accountAgent->name }}</label>
                                            </td>
                                            <td>{{ $accountAgent->email }}</td>
                                            <td>{{ ucfirst($accountAgent->account_role?->value ?? 'agent') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @error('support_agent_ids')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                        @error('support_agent_ids.*')
                            <p class="field-error">{{ $message }}</p>
                        @enderror

                        <p class="field-help">
                            Choose at least one support agent and keep at least one owner or admin assigned. Empty assignments are blocked here so site access does not accidentally reopen to the whole account.
                        </p>

                        <button class="button" type="submit">Save site access</button>
                    </form>
                @else
                    @if ($supportAgents->isEmpty())
                        <p class="empty">All account agents can support this site until an owner or admin configures explicit access.</p>
                    @else
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">Name</th>
                                        <th scope="col">Email</th>
                                        <th scope="col">Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($supportAgents as $supportAgent)
                                        <tr>
                                            <td>{{ $supportAgent->name }}</td>
                                            <td>{{ $supportAgent->email }}</td>
                                            <td>{{ ucfirst($supportAgent->account_role?->value ?? 'agent') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <p class="empty">Account owners and admins manage site support access.</p>
                @endif
            </section>

            <section class="section" aria-labelledby="data-responsibility-heading">
                <div class="section-header">
                    <h2 id="data-responsibility-heading">Data responsibility</h2>
                    <span class="lede">{{ $dataResponsibility['label'] }}</span>
                </div>

                <div class="notice-copy">
                    <p>{{ $dataResponsibility['message'] }}</p>
                    <p>{{ $dataResponsibility['guidance'] }}</p>
                </div>
            </section>

            <section class="section" aria-labelledby="install-snippet-heading">
                <div class="section-header">
                    <h2 id="install-snippet-heading">Install snippet</h2>
                    <span class="lede">Copy-ready widget script</span>
                </div>

                <div class="notice-copy">
                    <p>Paste this before the closing <code>&lt;/body&gt;</code> tag on the site where Wayfindr should appear.</p>
                </div>

                <pre class="code-block"><code>{{ $widgetInstallSnippet }}</code></pre>
            </section>

            <section class="section" aria-labelledby="privacy-settings-heading">
                <div class="section-header">
                    <h2 id="privacy-settings-heading">Mask selectors</h2>
                    <span class="lede">{{ count($maskSelectors) }} configured</span>
                </div>

                @if ($canUpdatePrivacy)
                    <form class="section-form" method="POST" action="{{ route('dashboard.sites.update', $site) }}">
                        @csrf
                        @method('PUT')

                        <div class="field">
                            <label for="mask_selectors">Selectors to mask before cobrowse sharing</label>
                            <textarea id="mask_selectors" name="mask_selectors" spellcheck="false">{{ old('mask_selectors', implode("\n", $maskSelectors)) }}</textarea>
                            @error('mask_selectors')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <p class="field-help">
                            Add one CSS selector per line. These selectors are sent to the widget as public configuration, so do not put private notes or secrets here.
                        </p>

                        <div class="notice-list">
                            <p><code>data-wayfindr-mask</code> and <code>data-wayfindr-private</code> force masking for known sensitive areas.</p>
                            <p><code>data-wayfindr-allow</code> is only for deliberate false positives where the content is safe to share.</p>
                        </div>

                        <button class="button" type="submit">Save privacy settings</button>
                    </form>
                @else
                    <div class="notice-copy">
                        <p>Account owners and admins manage privacy settings.</p>
                    </div>

                    @if (count($maskSelectors) === 0)
                        <p class="empty">No custom mask selectors are configured.</p>
                    @else
                        <div class="notice-list">
                            @foreach ($maskSelectors as $maskSelector)
                                <p><code>{{ $maskSelector }}</code></p>
                            @endforeach
                        </div>
                    @endif

                    <div class="notice-list">
                        <p><code>data-wayfindr-mask</code> and <code>data-wayfindr-private</code> force masking for known sensitive areas.</p>
                        <p><code>data-wayfindr-allow</code> is only for deliberate false positives where the content is safe to share.</p>
                    </div>
                @endif
            </section>
        </main>
    </div>
</x-layouts.app>
