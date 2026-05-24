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
                </div>
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

            <section class="section" aria-labelledby="privacy-settings-heading">
                <div class="section-header">
                    <h2 id="privacy-settings-heading">Mask selectors</h2>
                    <span class="lede">{{ count($maskSelectors) }} configured</span>
                </div>

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
            </section>
        </main>
    </div>
</x-layouts.app>
