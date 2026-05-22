<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Site;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentConversationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_open_conversations_for_the_agent_account(): void
    {
        $account = Account::factory()->create(['name' => 'Acme Support']);
        $otherAccount = Account::factory()->create(['name' => 'Other Support']);
        $agent = User::factory()->for($account)->create();

        $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
        $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
        $conversation = Conversation::factory()->for($site)->for($visitor)->create([
            'support_code' => 'WF-ACME123',
            'subject' => 'Checkout trouble',
            'status' => 'open',
            'last_message_at' => now()->subMinute(),
        ]);

        ConversationMessage::factory()->for($conversation)->create([
            'sender_type' => Visitor::class,
            'sender_id' => $visitor->id,
            'body' => 'The checkout button is stuck.',
            'created_at' => now()->subMinute(),
        ]);

        $closedConversation = Conversation::factory()->for($site)->for($visitor)->create([
            'support_code' => 'WF-CLOSED1',
            'subject' => 'Closed conversation',
            'status' => 'closed',
        ]);

        $otherSite = Site::factory()->for($otherAccount)->create(['name' => 'Other Docs']);
        $otherVisitor = Visitor::factory()->for($otherSite)->create(['anonymous_id' => 'anon-other']);
        Conversation::factory()->for($otherSite)->for($otherVisitor)->create([
            'support_code' => 'WF-OTHER1',
            'subject' => 'Other account problem',
            'status' => 'open',
        ]);

        $this->actingAs($agent)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Conversations')
            ->assertSee('Checkout trouble')
            ->assertSee('Acme Docs')
            ->assertSee('anon-acme')
            ->assertSee('WF-ACME123')
            ->assertDontSee($closedConversation->subject)
            ->assertDontSee('Other account problem')
            ->assertDontSee('Other Docs');
    }

    public function test_dashboard_shows_an_empty_conversation_state(): void
    {
        $account = Account::factory()->create();
        $agent = User::factory()->for($account)->create();

        $this->actingAs($agent)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('No active conversations yet.');
    }

    public function test_agent_can_view_their_account_conversation_timeline(): void
    {
        $account = Account::factory()->create(['name' => 'Acme Support']);
        $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
        $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
        $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
        $conversation = Conversation::factory()->for($site)->for($visitor)->create([
            'support_code' => 'WF-DETAIL1',
            'subject' => 'Checkout trouble',
            'status' => 'open',
        ]);

        ConversationMessage::factory()->for($conversation)->create([
            'sender_type' => Visitor::class,
            'sender_id' => $visitor->id,
            'body' => 'First visitor message.',
            'created_at' => now()->subMinutes(2),
        ]);

        ConversationMessage::factory()->for($conversation)->create([
            'sender_type' => User::class,
            'sender_id' => $agent->id,
            'body' => 'First agent note.',
            'created_at' => now()->subMinute(),
        ]);

        $this->actingAs($agent)
            ->get('/dashboard/conversations/WF-DETAIL1')
            ->assertOk()
            ->assertSee('Checkout trouble')
            ->assertSee('Acme Docs')
            ->assertSee('anon-acme')
            ->assertSee('WF-DETAIL1')
            ->assertSeeInOrder(['First visitor message.', 'First agent note.']);
    }

    public function test_agent_cannot_view_another_account_conversation(): void
    {
        $account = Account::factory()->create();
        $otherAccount = Account::factory()->create();
        $agent = User::factory()->for($account)->create();
        $otherSite = Site::factory()->for($otherAccount)->create();
        $otherVisitor = Visitor::factory()->for($otherSite)->create();

        Conversation::factory()->for($otherSite)->for($otherVisitor)->create([
            'support_code' => 'WF-OTHER1',
        ]);

        $this->actingAs($agent)
            ->get('/dashboard/conversations/WF-OTHER1')
            ->assertNotFound();
    }
}
