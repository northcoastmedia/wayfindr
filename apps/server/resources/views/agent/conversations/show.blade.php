<x-layouts.app title="Conversation {{ $conversation->support_code }}">
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
            <h1>{{ $conversation->subject ?? 'Untitled conversation' }}</h1>
            <p class="lede">Support code {{ $conversation->support_code }}</p>

            <section class="section" aria-labelledby="conversation-context-heading">
                <div class="section-header">
                    <h2 id="conversation-context-heading">Context</h2>
                    <span class="lede">{{ ucfirst($conversation->status) }}</span>
                </div>

                <div class="meta-grid">
                    <div class="meta-item">
                        <span class="meta-label">Site</span>
                        <span class="meta-value">{{ $conversation->site->name }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Visitor</span>
                        <span class="meta-value">{{ $conversation->visitor->anonymous_id ?? 'Unknown visitor' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Opened</span>
                        <span class="meta-value">{{ $conversation->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Last Activity</span>
                        <span class="meta-value">{{ $conversation->last_message_at?->diffForHumans() ?? 'No messages yet' }}</span>
                    </div>
                </div>
            </section>

            <section class="section" aria-labelledby="messages-heading">
                <div class="section-header">
                    <h2 id="messages-heading">Messages</h2>
                    <span class="lede">{{ $messages->count() }} total</span>
                </div>

                @if ($messages->isEmpty())
                    <p class="empty">No messages yet.</p>
                @else
                    <div class="message-list">
                        @foreach ($messages as $message)
                            @php
                                $isAgent = $message->sender_type === \App\Models\User::class;
                                $senderName = $isAgent
                                    ? ($message->sender?->name ?? 'Agent')
                                    : 'Visitor';
                            @endphp
                            <article class="message {{ $isAgent ? 'agent' : 'visitor' }}">
                                <div class="message-meta">
                                    <strong>{{ $senderName }}</strong>
                                    <span>{{ $message->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="message-body">{{ $message->body }}</p>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </main>
    </div>
</x-layouts.app>
