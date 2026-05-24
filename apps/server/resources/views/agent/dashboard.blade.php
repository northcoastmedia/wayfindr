<x-layouts.app title="Agent Dashboard">
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
            <h1>{{ $account->name }}</h1>
            <p class="lede">Signed in as {{ $agent->email }}</p>

            <section class="section" aria-labelledby="data-responsibility-heading">
                <div class="section-header">
                    <h2 id="data-responsibility-heading">Data responsibility</h2>
                    <span class="lede">{{ $dataResponsibility['label'] }}</span>
                </div>

                <div class="notice-copy">
                    <p>{{ $dataResponsibility['message'] }}</p>
                    <p>{{ $dataResponsibility['guidance'] }}</p>
                    <p>
                        <a class="text-link" href="{{ $dataResponsibility['docs_url'] }}" target="_blank" rel="noreferrer">
                            Review the data responsibility docs
                        </a>
                    </p>
                </div>
            </section>

            <section class="section" aria-labelledby="sites-heading">
                <div class="section-header">
                    <h2 id="sites-heading">Sites</h2>
                    <div class="section-actions">
                        <span class="lede">{{ $sites->count() }} active</span>
                        <a class="button secondary" href="{{ route('dashboard.sites.create') }}">Add site</a>
                    </div>
                </div>

                @if ($sites->isEmpty())
                    <p class="empty">No sites have been connected yet.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Domain</th>
                                    <th scope="col">Public Key</th>
                                    <th scope="col">Last check-in</th>
                                    <th scope="col">Last page</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sites as $site)
                                    @php
                                        $latestVisitor = $site->latestVisitor;
                                        $lastPageUrl = data_get($latestVisitor?->metadata, 'last_page_url');
                                    @endphp
                                    <tr>
                                        <td>
                                            <a class="text-link" href="{{ route('dashboard.sites.show', $site) }}">
                                                {{ $site->name }}
                                            </a>
                                        </td>
                                        <td>{{ $site->domain ?? 'Not set' }}</td>
                                        <td>{{ $site->public_key }}</td>
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

            <section class="section" aria-labelledby="realtime-heading">
                <div class="section-header">
                    <h2 id="realtime-heading">Realtime</h2>
                    <span class="lede">{{ $realtimeHealth['label'] }}</span>
                </div>

                <div class="meta-grid realtime-grid">
                    <div class="meta-item">
                        <span class="meta-label">Broadcast driver</span>
                        <span class="meta-value">{{ $realtimeHealth['driver'] }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Endpoint</span>
                        <span class="meta-value">{{ $realtimeHealth['endpoint'] }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Scheme</span>
                        <span class="meta-value">{{ $realtimeHealth['scheme'] }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">App ID</span>
                        <span class="meta-value">{{ $realtimeHealth['has_app_id'] ? 'Set' : 'Missing' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">App key</span>
                        <span class="meta-value">{{ $realtimeHealth['has_app_key'] ? 'Set' : 'Missing' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Secret</span>
                        <span class="meta-value">{{ $realtimeHealth['has_app_secret'] ? 'Set' : 'Missing' }}</span>
                    </div>
                </div>

                <p class="empty realtime-note">{{ $realtimeHealth['message'] }}</p>
            </section>

            <section class="section" aria-labelledby="tickets-heading">
                <div class="section-header">
                    <h2 id="tickets-heading">Tickets</h2>
                    <span class="lede">{{ $tickets->count() }} open</span>
                </div>

                @if ($tickets->isEmpty())
                    <p class="empty">No open tickets yet.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Subject</th>
                                    <th scope="col">Site</th>
                                    <th scope="col">Priority</th>
                                    <th scope="col">Support Code</th>
                                    <th scope="col">Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tickets as $ticket)
                                    <tr>
                                        <td>
                                            @if ($ticket->conversation)
                                                <a class="text-link" href="{{ route('dashboard.conversations.show', $ticket->conversation->support_code) }}">
                                                    {{ $ticket->subject }}
                                                </a>
                                            @else
                                                {{ $ticket->subject }}
                                            @endif
                                        </td>
                                        <td>{{ $ticket->site->name }}</td>
                                        <td>{{ ucfirst($ticket->priority) }}</td>
                                        <td>{{ $ticket->conversation?->support_code ?? 'Not linked' }}</td>
                                        <td>{{ $ticket->updated_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="section" aria-labelledby="conversations-heading">
                <div class="section-header">
                    <h2 id="conversations-heading">Conversations</h2>
                    <span class="lede">{{ $conversations->count() }} open</span>
                </div>

                @if ($conversations->isEmpty())
                    <p class="empty">No active conversations yet.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Subject</th>
                                    <th scope="col">Site</th>
                                    <th scope="col">Visitor</th>
                                    <th scope="col">Support Code</th>
                                    <th scope="col">Last Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($conversations as $conversation)
                                    <tr>
                                        <td>
                                            <a class="text-link" href="{{ route('dashboard.conversations.show', $conversation->support_code) }}">
                                                {{ $conversation->subject ?? 'Untitled conversation' }}
                                            </a>
                                        </td>
                                        <td>{{ $conversation->site->name }}</td>
                                        <td>{{ $conversation->visitor->anonymous_id ?? 'Unknown visitor' }}</td>
                                        <td>{{ $conversation->support_code }}</td>
                                        <td>{{ $conversation->last_message_at?->diffForHumans() ?? $conversation->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </main>
    </div>
</x-layouts.app>
