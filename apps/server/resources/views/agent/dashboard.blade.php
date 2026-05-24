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

            <section class="section" aria-labelledby="alerts-heading">
                <div class="section-header">
                    <h2 id="alerts-heading">Alerts</h2>
                    <div class="section-actions">
                        <span class="lede">{{ $unreadNotificationCount }} unread</span>
                        @if ($unreadNotificationCount > 0)
                            <form method="POST" action="{{ route('dashboard.alerts.read-all') }}">
                                @csrf
                                <button class="button secondary" type="submit">Mark all read</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($unreadNotifications->isEmpty())
                    <p class="empty">You’re caught up.</p>
                @else
                    <div class="message-list">
                        @foreach ($unreadNotifications as $notification)
                            @php
                                $notificationData = $notification->data;
                                $messageCount = max(1, (int) data_get($notificationData, 'message_count', 1));
                            @endphp
                            <article class="message">
                                <div class="message-meta">
                                    <strong>{{ data_get($notificationData, 'subject', 'Untitled conversation') }}</strong>
                                    <span>{{ $notification->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="lede">
                                    {{ $messageCount === 1 ? '1 new message' : $messageCount.' new messages' }}
                                </p>
                                <p class="message-body">{{ data_get($notificationData, 'message_preview') }}</p>
                                <p class="lede">
                                    <a class="text-link" href="{{ data_get($notificationData, 'url') }}">
                                        {{ data_get($notificationData, 'support_code') }}
                                    </a>
                                    on {{ data_get($notificationData, 'site_name', 'Unknown site') }}
                                </p>
                                <form method="POST" action="{{ route('dashboard.alerts.read', $notification) }}">
                                    @csrf
                                    <button class="button secondary" type="submit">Mark read</button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="section" aria-labelledby="team-heading">
                <div class="section-header">
                    <h2 id="team-heading">Team</h2>
                    <span class="lede">{{ $agents->count() }} active</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Open conversations</th>
                                <th scope="col">Open tickets</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($agents as $accountAgent)
                                <tr>
                                    <td>{{ $accountAgent->name }}</td>
                                    <td>{{ $accountAgent->email }}</td>
                                    <td>{{ $accountAgent->open_assigned_conversations_count }}</td>
                                    <td>{{ $accountAgent->open_assigned_tickets_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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
                    <div class="section-actions">
                        <span class="lede">{{ $tickets->count() }} open</span>
                        @foreach ($ticketFilters as $filterValue => $filterLabel)
                            <a
                                class="button {{ $ticketFilter === $filterValue ? '' : 'secondary' }}"
                                href="{{ route('dashboard', $filterValue === 'all' ? [] : ['ticket_filter' => $filterValue]) }}"
                                @if ($ticketFilter === $filterValue) aria-current="page" @endif
                            >
                                {{ $filterLabel }}
                            </a>
                        @endforeach
                    </div>
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
                                    <th scope="col">Assignee</th>
                                    <th scope="col">Support Code</th>
                                    <th scope="col">Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tickets as $ticket)
                                    <tr>
                                        <td>
                                            <a class="text-link" href="{{ route('dashboard.tickets.show', $ticket) }}">
                                                {{ $ticket->subject }}
                                            </a>
                                        </td>
                                        <td>{{ $ticket->site->name }}</td>
                                        <td>{{ ucfirst($ticket->priority) }}</td>
                                        <td>{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
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
                    <div class="section-actions">
                        <span class="lede">{{ $conversations->count() }} open</span>
                        @foreach ($conversationFilters as $filterValue => $filterLabel)
                            <a
                                class="button {{ $conversationFilter === $filterValue ? '' : 'secondary' }}"
                                href="{{ route('dashboard', $filterValue === 'all' ? [] : ['conversation_filter' => $filterValue]) }}"
                                @if ($conversationFilter === $filterValue) aria-current="page" @endif
                            >
                                {{ $filterLabel }}
                            </a>
                        @endforeach
                    </div>
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
                                    <th scope="col">Assigned</th>
                                    <th scope="col">Attention</th>
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
                                        <td>{{ $conversation->assignedAgent?->name ?? 'Unassigned' }}</td>
                                        <td>{{ $conversation->attentionLabel() }}</td>
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
