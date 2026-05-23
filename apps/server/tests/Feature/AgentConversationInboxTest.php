<?php

use App\Models\Account;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Site;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard lists open conversations for the agent account', function (): void {
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
});

test('dashboard shows an empty conversation state', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('No active conversations yet.');
});

test('agent can view their account conversation timeline', function (): void {
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
        ->assertSee('Send reply')
        ->assertSee('name="body"', false)
        ->assertSeeInOrder(['First visitor message.', 'First agent note.']);
});

test('agent can reply to their account conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-REPLY1',
        'subject' => 'Checkout trouble',
        'status' => 'open',
        'last_message_at' => now()->subMinutes(5),
    ]);

    ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'The checkout button is stuck.',
        'created_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($agent)
        ->post('/dashboard/conversations/WF-REPLY1/messages', [
            'body' => 'Thanks, I can help.',
        ])
        ->assertRedirect('/dashboard/conversations/WF-REPLY1');

    $this->assertDatabaseHas('conversation_messages', [
        'conversation_id' => $conversation->id,
        'sender_type' => User::class,
        'sender_id' => $agent->id,
        'type' => 'text',
        'body' => 'Thanks, I can help.',
    ]);

    expect($conversation->fresh()->last_message_at->greaterThan(now()->subMinutes(2)))->toBeTrue();

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-REPLY1')
        ->assertOk()
        ->assertSee('Ada Agent')
        ->assertSeeInOrder(['The checkout button is stuck.', 'Thanks, I can help.']);
});

test('agent reply requires a message body', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $site = Site::factory()->for($account)->create();
    $visitor = Visitor::factory()->for($site)->create();
    Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-REPLY2',
    ]);

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-REPLY2')
        ->post('/dashboard/conversations/WF-REPLY2/messages', [
            'body' => '',
        ])
        ->assertRedirect('/dashboard/conversations/WF-REPLY2')
        ->assertSessionHasErrors('body');

    $this->assertDatabaseCount('conversation_messages', 0);
});

test('agent cannot reply to another account conversation', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $otherSite = Site::factory()->for($otherAccount)->create();
    $otherVisitor = Visitor::factory()->for($otherSite)->create();

    Conversation::factory()->for($otherSite)->for($otherVisitor)->create([
        'support_code' => 'WF-OTHER1',
    ]);

    $this->actingAs($agent)
        ->post('/dashboard/conversations/WF-OTHER1/messages', [
            'body' => 'Can you see this?',
        ])
        ->assertNotFound();

    $this->assertDatabaseMissing('conversation_messages', [
        'body' => 'Can you see this?',
    ]);
});

test('agent cannot view another account conversation', function (): void {
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
});
