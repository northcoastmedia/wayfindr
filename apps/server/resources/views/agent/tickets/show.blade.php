<x-layouts.app title="Ticket - {{ $ticket->subject }}">
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
            <h1>{{ $ticket->subject }}</h1>
            <p class="lede">Ticket #{{ $ticket->id }}</p>

            @if (session('status'))
                <p class="status-message">{{ session('status') }}</p>
            @endif

            <section class="section" aria-labelledby="ticket-context-heading">
                <div class="section-header">
                    <h2 id="ticket-context-heading">Context</h2>
                    <span class="lede">{{ ucfirst($ticket->status) }}</span>
                </div>

                <div class="meta-grid">
                    <div class="meta-item">
                        <span class="meta-label">Site</span>
                        <span class="meta-value">{{ $ticket->site->name }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Requester</span>
                        <span class="meta-value">{{ $ticket->requester?->anonymous_id ?? 'Not linked' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Priority</span>
                        <span class="meta-value">{{ ucfirst($ticket->priority) }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Assignee</span>
                        <span class="meta-value">{{ $ticket->assignee?->name ?? 'Unassigned' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Created</span>
                        <span class="meta-value">{{ $ticket->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Updated</span>
                        <span class="meta-value">{{ $ticket->updated_at->diffForHumans() }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Closed</span>
                        <span class="meta-value">{{ $ticket->closed_at?->diffForHumans() ?? 'Not closed' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Conversation</span>
                        <span class="meta-value">
                            @if ($ticket->conversation)
                                <a class="text-link" href="{{ route('dashboard.conversations.show', $ticket->conversation->support_code) }}">
                                    {{ $ticket->conversation->support_code }}
                                </a>
                            @else
                                Not linked
                            @endif
                        </span>
                    </div>
                </div>
            </section>

            @if ($ticket->conversation)
                <section class="section" aria-labelledby="linked-conversation-heading">
                    <div class="section-header">
                        <h2 id="linked-conversation-heading">Linked conversation</h2>
                        <span class="lede">{{ ucfirst($ticket->conversation->status) }}</span>
                    </div>

                    <div class="notice-copy">
                        <p>{{ $ticket->conversation->subject ?? 'Untitled conversation' }}</p>
                    </div>
                </section>
            @endif

            <section class="section" aria-labelledby="ticket-actions-heading">
                <div class="section-header">
                    <h2 id="ticket-actions-heading">Actions</h2>
                    <span class="lede">{{ $ticket->assignee?->name ?? 'Unassigned' }}</span>
                </div>

                <form class="section-form" method="POST" action="{{ route('dashboard.tickets.assignee.update', $ticket) }}">
                    @csrf
                    @method('PUT')

                    <div class="field">
                        <label for="assignee_id">Assign ticket</label>
                        <select id="assignee_id" name="assignee_id">
                            <option value="">Unassigned</option>
                            @foreach ($accountAgents as $accountAgent)
                                <option value="{{ $accountAgent->id }}" @selected((int) $ticket->assignee_id === $accountAgent->id)>
                                    {{ $accountAgent->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('assignee_id')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button class="button secondary" type="submit">Assign ticket</button>
                </form>

                @if ($ticket->status === 'open')
                    <form class="section-form" method="POST" action="{{ route('dashboard.tickets.pending', $ticket) }}">
                        @csrf
                        <button class="button secondary" type="submit">Mark pending</button>
                    </form>
                @endif

                @if (in_array($ticket->status, ['closed', 'pending'], true))
                    <form class="section-form" method="POST" action="{{ route('dashboard.tickets.reopen', $ticket) }}">
                        @csrf
                        <button class="button secondary" type="submit">Reopen ticket</button>
                    </form>
                @endif

                @if ($ticket->status !== 'closed')
                    <form class="section-form" method="POST" action="{{ route('dashboard.tickets.close', $ticket) }}">
                        @csrf
                        <button class="button secondary" type="submit">Close ticket</button>
                    </form>
                @endif
            </section>

            <section class="section" aria-labelledby="ticket-details-heading">
                <div class="section-header">
                    <h2 id="ticket-details-heading">Ticket details</h2>
                    <span class="lede">{{ ucfirst($ticket->priority) }}</span>
                </div>

                <form class="section-form" method="POST" action="{{ route('dashboard.tickets.update', $ticket) }}">
                    @csrf
                    @method('PUT')

                    <div class="field">
                        <label for="subject">Subject</label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject', $ticket->subject) }}">
                        @error('subject')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority">
                            @foreach (['low', 'normal', 'high', 'urgent'] as $priority)
                                <option value="{{ $priority }}" @selected(old('priority', $ticket->priority) === $priority)>
                                    {{ ucfirst($priority) }}
                                </option>
                            @endforeach
                        </select>
                        @error('priority')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="6">{{ old('description', $ticket->description) }}</textarea>
                        @error('description')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button class="button" type="submit">Save ticket</button>
                </form>
            </section>

            <section class="section" aria-labelledby="ticket-notes-heading">
                <div class="section-header">
                    <h2 id="ticket-notes-heading">Internal notes</h2>
                    <span class="lede">{{ $ticket->auditEvents->count() }} total</span>
                </div>

                <div class="message-list">
                    @forelse ($ticket->auditEvents as $note)
                        <article class="message-card agent-message">
                            <div class="message-meta">
                                <strong>{{ $note->actor?->name ?? 'Unknown agent' }}</strong>
                                <span>{{ $note->occurred_at->diffForHumans() }}</span>
                            </div>
                            <p>{{ data_get($note->metadata, 'body') }}</p>
                        </article>
                    @empty
                        <div class="empty-state">No internal notes yet.</div>
                    @endforelse
                </div>

                <form class="section-form" method="POST" action="{{ route('dashboard.tickets.notes.store', $ticket) }}">
                    @csrf

                    <div class="field">
                        <label for="body">Add internal note</label>
                        <textarea id="body" name="body" rows="4" placeholder="Document follow-up, escalation context, or handoff details.">{{ old('body') }}</textarea>
                        @error('body')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button class="button" type="submit">Add note</button>
                </form>
            </section>

            <section class="section" aria-labelledby="ticket-activity-heading">
                <div class="section-header">
                    <h2 id="ticket-activity-heading">Activity</h2>
                    <span class="lede">{{ $ticketActivity->count() }} total</span>
                </div>

                <div class="message-list">
                    @forelse ($ticketActivity as $activity)
                        <article class="message-card">
                            <div class="message-meta">
                                <strong>{{ $activity->actor?->name ?? 'System' }}</strong>
                                <span>{{ $activity->occurred_at->diffForHumans() }}</span>
                            </div>
                            <p>
                                @switch($activity->action)
                                    @case('ticket.closed')
                                        Ticket closed
                                        @break

                                    @case('ticket.pending')
                                        Ticket marked pending
                                        @break

                                    @case('ticket.reopened')
                                        Ticket reopened
                                        @break

                                    @case('ticket.note_added')
                                        Internal note added
                                        @break

                                    @case('ticket.assignee_updated')
                                        Assignee changed from {{ data_get($activity->metadata, 'old_assignee_name') ?? 'Unassigned' }} to {{ data_get($activity->metadata, 'new_assignee_name') ?? 'Unassigned' }}
                                        @break

                                    @case('ticket.updated')
                                        @foreach (data_get($activity->metadata, 'changes', []) as $field => $change)
                                            @if ($field === 'description')
                                                <span>Description updated</span>@if (! $loop->last)<br>@endif
                                            @elseif ($field === 'priority')
                                                <span>Priority changed from {{ ucfirst(data_get($change, 'old')) }} to {{ ucfirst(data_get($change, 'new')) }}</span>@if (! $loop->last)<br>@endif
                                            @else
                                                <span>{{ ucfirst($field) }} changed from {{ data_get($change, 'old') }} to {{ data_get($change, 'new') }}</span>@if (! $loop->last)<br>@endif
                                            @endif
                                        @endforeach
                                        @break

                                    @default
                                        {{ ucfirst(str_replace(['ticket.', '_'], ['', ' '], $activity->action)) }}
                                @endswitch
                            </p>
                        </article>
                    @empty
                        <div class="empty-state">No ticket activity yet.</div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
</x-layouts.app>
