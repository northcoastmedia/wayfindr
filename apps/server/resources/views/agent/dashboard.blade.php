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

            <section class="section" aria-labelledby="sites-heading">
                <div class="section-header">
                    <h2 id="sites-heading">Sites</h2>
                    <span class="lede">{{ $sites->count() }} active</span>
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
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sites as $site)
                                    <tr>
                                        <td>{{ $site->name }}</td>
                                        <td>{{ $site->domain ?? 'Not set' }}</td>
                                        <td>{{ $site->public_key }}</td>
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
                    <span class="lede">0 open</span>
                </div>
                <p class="empty">No active conversations yet.</p>
            </section>
        </main>
    </div>
</x-layouts.app>
