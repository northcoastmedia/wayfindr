<x-layouts.app title="Sites" :agent="$agent" :account="$account">
            <div class="section-header">
                <div>
                    <h1>Sites</h1>
                    <p class="lede">Manage widget installs, support access, privacy rules, and issue routing.</p>
                </div>
                <div class="section-actions">
                    <span class="lede">{{ $sites->count() }} visible</span>
                    <a class="button secondary" href="{{ route('dashboard.sites.create') }}">Add site</a>
                </div>
            </div>

            @if (session('status'))
                <p class="status-message">{{ session('status') }}</p>
            @endif

            <section class="section" aria-labelledby="sites-heading">
                <div class="section-header">
                    <h2 id="sites-heading">Connected sites</h2>
                    <span class="lede">Visible to your support role</span>
                </div>

                @if ($sites->isEmpty())
                    <div class="notice-copy">
                        <p>No sites are visible to you yet. Add the first site to get a public key and widget install snippet.</p>
                    </div>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Site</th>
                                    <th scope="col">Access</th>
                                    <th scope="col">Last check-in</th>
                                    <th scope="col">Last page</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sites as $site)
                                    @php
                                        $latestVisitor = $site->latestVisitor;
                                        $lastPageUrl = data_get($latestVisitor?->metadata, 'last_page_url');
                                        $supportAgentCount = (int) $site->support_agents_count;
                                    @endphp
                                    <tr>
                                        <td>
                                            <a class="text-link" href="{{ route('dashboard.sites.show', $site) }}">{{ $site->name }}</a>
                                            <span class="lede">{{ $site->domain ?? 'Not set' }}</span>
                                        </td>
                                        <td>
                                            @if ($supportAgentCount > 0)
                                                <strong>Explicit access</strong>
                                                <span class="lede">{{ $supportAgentCount }} assigned</span>
                                            @else
                                                <strong>Account-wide fallback</strong>
                                                <span class="lede">All account agents</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($latestVisitor?->last_seen_at)
                                                Seen {{ $latestVisitor->last_seen_at->diffForHumans() }}
                                            @else
                                                Not seen yet
                                            @endif
                                        </td>
                                        <td>{{ $lastPageUrl ?: 'Not reported' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
</x-layouts.app>
