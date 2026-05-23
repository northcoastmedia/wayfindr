<?php

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Site;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('widget bootstrap creates a site scoped visitor and returns safe config', function (): void {
    $site = Site::factory()->create([
        'name' => 'Docs Site',
        'domain' => 'docs.example.test',
        'public_key' => 'site_public_docs',
        'settings' => [
            'mask_selectors' => ['input[type="password"]', '[data-secret]'],
            'internal_note' => 'do not leak this',
        ],
    ]);

    $response = $this->postJson('/api/widget/bootstrap', [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-browser-123',
        'page_url' => 'https://docs.example.test/install',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.site.public_key', 'site_public_docs')
        ->assertJsonPath('data.site.name', 'Docs Site')
        ->assertJsonPath('data.site.settings.mask_selectors', ['input[type="password"]', '[data-secret]'])
        ->assertJsonPath('data.visitor.anonymous_id', 'anon-browser-123');

    $payload = $response->json('data');

    expect($payload['site'])
        ->not->toHaveKey('id')
        ->not->toHaveKey('account_id');

    expect($payload['site']['settings'])->not->toHaveKey('internal_note');
    expect($payload['visitor']['token'])->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('visitors', [
        'site_id' => $site->id,
        'anonymous_id' => 'anon-browser-123',
    ]);
});

test('widget bootstrap rejects an unknown public key', function (): void {
    $this->postJson('/api/widget/bootstrap', [
        'site_public_key' => 'missing_key',
        'anonymous_id' => 'anon-browser-123',
    ])
        ->assertNotFound()
        ->assertJsonPath('message', 'Site not found.');
});

test('conversation creation uses the site scoped visitor', function (): void {
    $site = Site::factory()->create(['public_key' => 'site_public_docs']);
    $otherSite = Site::factory()->create(['public_key' => 'site_public_other']);

    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'shared-anon']);
    Visitor::factory()->for($otherSite)->create(['anonymous_id' => 'shared-anon']);
    $token = widgetVisitorToken($this, 'site_public_docs', 'shared-anon');

    $response = $this->postJson('/api/conversations', [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'shared-anon',
        'visitor_token' => $token,
        'subject' => 'Need help installing',
        'page_url' => 'https://docs.example.test/install',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.subject', 'Need help installing')
        ->assertJsonPath('data.visitor.anonymous_id', 'shared-anon');

    $supportCode = $response->json('data.support_code');

    expect($supportCode)->toBeString()->toStartWith('WF-');

    $this->assertDatabaseHas('conversations', [
        'site_id' => $site->id,
        'visitor_id' => $visitor->id,
        'subject' => 'Need help installing',
        'status' => 'open',
    ]);
});

test('conversation creation requires a visitor token', function (): void {
    Site::factory()->create(['public_key' => 'site_public_docs']);

    $this->postJson('/api/conversations', [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'subject' => 'Need help installing',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Visitor token is required.');
});

test('conversation creation rejects a token for another visitor', function (): void {
    $site = Site::factory()->create(['public_key' => 'site_public_docs']);
    Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $token = widgetVisitorToken($this, 'site_public_docs', 'anon-other');

    $this->postJson('/api/conversations', [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => $token,
        'subject' => 'Need help installing',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Visitor token does not match this visitor.');
});

test('conversation creation rejects a token for another site', function (): void {
    Site::factory()->create(['public_key' => 'site_public_docs']);
    Site::factory()->create(['public_key' => 'site_public_other']);
    $token = widgetVisitorToken($this, 'site_public_other', 'anon-other');

    $this->postJson('/api/conversations', [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-other',
        'visitor_token' => $token,
        'subject' => 'Need help installing',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Visitor token does not match this site.');
});

test('visitor message creation cannot cross site boundaries', function (): void {
    $site = Site::factory()->create(['public_key' => 'site_public_docs']);
    $otherSite = Site::factory()->create(['public_key' => 'site_public_other']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $otherVisitor = Visitor::factory()->for($otherSite)->create(['anonymous_id' => 'anon-other']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-BOUNDARY',
    ]);

    $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
        'site_public_key' => 'site_public_other',
        'anonymous_id' => 'anon-other',
        'visitor_token' => widgetVisitorToken($this, 'site_public_other', 'anon-other'),
        'body' => 'This should not land in the docs conversation.',
    ])
        ->assertNotFound();

    $this->assertDatabaseMissing('conversation_messages', [
        'conversation_id' => $conversation->id,
        'sender_id' => $otherVisitor->id,
    ]);
});

test('visitor can add a message to their conversation', function (): void {
    $site = Site::factory()->create(['public_key' => 'site_public_docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-MESSAGE',
    ]);
    $token = widgetVisitorToken($this, 'site_public_docs', 'anon-docs');

    $response = $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => $token,
        'body' => 'Can you help me with this checkout error?',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.conversation.support_code', 'WF-MESSAGE')
        ->assertJsonPath('data.message.type', 'text')
        ->assertJsonPath('data.message.body', 'Can you help me with this checkout error?');

    $message = ConversationMessage::query()->firstOrFail();

    expect($message->conversation_id)->toBe($conversation->id)
        ->and($message->sender_type)->toBe(Visitor::class)
        ->and($message->sender_id)->toBe($visitor->id)
        ->and($conversation->refresh()->last_message_at)->not->toBeNull();
});

test('visitor message creation rejects an invalid token', function (): void {
    $site = Site::factory()->create(['public_key' => 'site_public_docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-MESSAGE',
    ]);

    $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => 'not-a-real-token',
        'body' => 'Can you help me with this checkout error?',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Visitor token is invalid.');
});

test('visitor can read their conversation messages', function (): void {
    $site = Site::factory()->create(['public_key' => 'site_public_docs']);
    $agent = User::factory()->create(['name' => 'Ada Agent']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-MESSAGES',
    ]);

    ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'Hello from the visitor.',
        'created_at' => now()->subMinute(),
    ]);

    ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => User::class,
        'sender_id' => $agent->id,
        'body' => 'Hello from support.',
        'created_at' => now(),
    ]);

    $response = $this->getJson('/api/conversations/WF-MESSAGES/messages?'.http_build_query([
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => widgetVisitorToken($this, 'site_public_docs', 'anon-docs'),
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('data.conversation.support_code', 'WF-MESSAGES')
        ->assertJsonPath('data.messages.0.sender.kind', 'visitor')
        ->assertJsonPath('data.messages.0.sender.name', 'Visitor')
        ->assertJsonPath('data.messages.0.body', 'Hello from the visitor.')
        ->assertJsonPath('data.messages.1.sender.kind', 'agent')
        ->assertJsonPath('data.messages.1.sender.name', 'Ada Agent')
        ->assertJsonPath('data.messages.1.body', 'Hello from support.');

    $payload = $response->json('data.messages.0');

    expect($payload)
        ->not->toHaveKey('sender_id')
        ->not->toHaveKey('sender_type');
});

test('visitor message read cannot cross site boundaries', function (): void {
    $site = Site::factory()->create(['public_key' => 'site_public_docs']);
    $otherSite = Site::factory()->create(['public_key' => 'site_public_other']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    Visitor::factory()->for($otherSite)->create(['anonymous_id' => 'anon-other']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-BOUNDARY',
    ]);

    ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'This should stay private to the docs visitor.',
    ]);

    $this->getJson('/api/conversations/WF-BOUNDARY/messages?'.http_build_query([
        'site_public_key' => 'site_public_other',
        'anonymous_id' => 'anon-other',
        'visitor_token' => widgetVisitorToken($this, 'site_public_other', 'anon-other'),
    ]))
        ->assertNotFound();
});

test('visitor message read rejects a token for another visitor', function (): void {
    $site = Site::factory()->create(['public_key' => 'site_public_docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-other']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-PRIVATE',
    ]);

    ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'This should stay private to the docs visitor.',
    ]);

    $this->getJson('/api/conversations/WF-PRIVATE/messages?'.http_build_query([
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-other',
        'visitor_token' => widgetVisitorToken($this, 'site_public_docs', 'anon-other'),
    ]))
        ->assertNotFound();
});

function widgetVisitorToken($test, string $sitePublicKey, string $anonymousId): string
{
    return $test->postJson('/api/widget/bootstrap', [
        'site_public_key' => $sitePublicKey,
        'anonymous_id' => $anonymousId,
        'page_url' => 'https://docs.example.test/install',
    ])
        ->assertSuccessful()
        ->json('data.visitor.token');
}
