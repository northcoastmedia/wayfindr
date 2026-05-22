<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Site;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetIntakeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_bootstrap_creates_a_site_scoped_visitor_and_returns_safe_config(): void
    {
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

        $this->assertArrayNotHasKey('id', $payload['site']);
        $this->assertArrayNotHasKey('account_id', $payload['site']);
        $this->assertArrayNotHasKey('internal_note', $payload['site']['settings']);
        $this->assertDatabaseHas('visitors', [
            'site_id' => $site->id,
            'anonymous_id' => 'anon-browser-123',
        ]);
    }

    public function test_widget_bootstrap_rejects_an_unknown_public_key(): void
    {
        $this->postJson('/api/widget/bootstrap', [
            'site_public_key' => 'missing_key',
            'anonymous_id' => 'anon-browser-123',
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Site not found.');
    }

    public function test_conversation_creation_uses_the_site_scoped_visitor(): void
    {
        $site = Site::factory()->create(['public_key' => 'site_public_docs']);
        $otherSite = Site::factory()->create(['public_key' => 'site_public_other']);

        $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'shared-anon']);
        Visitor::factory()->for($otherSite)->create(['anonymous_id' => 'shared-anon']);

        $response = $this->postJson('/api/conversations', [
            'site_public_key' => 'site_public_docs',
            'anonymous_id' => 'shared-anon',
            'subject' => 'Need help installing',
            'page_url' => 'https://docs.example.test/install',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.subject', 'Need help installing')
            ->assertJsonPath('data.visitor.anonymous_id', 'shared-anon');

        $supportCode = $response->json('data.support_code');

        $this->assertIsString($supportCode);
        $this->assertStringStartsWith('WF-', $supportCode);
        $this->assertDatabaseHas('conversations', [
            'site_id' => $site->id,
            'visitor_id' => $visitor->id,
            'subject' => 'Need help installing',
            'status' => 'open',
        ]);
    }

    public function test_visitor_message_creation_cannot_cross_site_boundaries(): void
    {
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
            'body' => 'This should not land in the docs conversation.',
        ])
            ->assertNotFound();

        $this->assertDatabaseMissing('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $otherVisitor->id,
        ]);
    }

    public function test_visitor_can_add_a_message_to_their_conversation(): void
    {
        $site = Site::factory()->create(['public_key' => 'site_public_docs']);
        $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
        $conversation = Conversation::factory()->for($site)->for($visitor)->create([
            'support_code' => 'WF-MESSAGE',
        ]);

        $response = $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
            'site_public_key' => 'site_public_docs',
            'anonymous_id' => 'anon-docs',
            'body' => 'Can you help me with this checkout error?',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.conversation.support_code', 'WF-MESSAGE')
            ->assertJsonPath('data.message.type', 'text')
            ->assertJsonPath('data.message.body', 'Can you help me with this checkout error?');

        $message = ConversationMessage::query()->firstOrFail();

        $this->assertSame($conversation->id, $message->conversation_id);
        $this->assertSame(Visitor::class, $message->sender_type);
        $this->assertSame($visitor->id, $message->sender_id);
        $this->assertNotNull($conversation->refresh()->last_message_at);
    }
}
