<?php

use App\Events\CobrowseStateUpdated;
use App\Models\Account;
use App\Models\CobrowseSession;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Site;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

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
        ->assertSee('/dashboard/sites/'.$site->id, false)
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

test('dashboard shows conversation assignment state', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $assignedAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);

    Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $assignedAgent->id,
        'support_code' => 'WF-ASSIGNED',
        'subject' => 'Assigned checkout trouble',
        'status' => 'open',
    ]);

    Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => null,
        'support_code' => 'WF-UNASSIGN',
        'subject' => 'Unassigned checkout trouble',
        'status' => 'open',
    ]);

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Assigned')
        ->assertSee('Assigned checkout trouble')
        ->assertSee('Bea Builder')
        ->assertSee('Unassigned checkout trouble')
        ->assertSee('Unassigned');
});

test('dashboard shows conversation attention state from the latest message', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);

    $needsReplyConversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-NEEDS1',
        'subject' => 'Visitor latest',
        'status' => 'open',
        'last_message_at' => now()->subMinutes(2),
    ]);
    ConversationMessage::factory()->for($needsReplyConversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'I still need help.',
        'created_at' => now()->subMinutes(2),
    ]);

    $waitingConversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-WAITING',
        'subject' => 'Agent latest',
        'status' => 'open',
        'last_message_at' => now()->subMinute(),
    ]);
    ConversationMessage::factory()->for($waitingConversation)->create([
        'sender_type' => User::class,
        'sender_id' => $agent->id,
        'body' => 'Can you try again?',
        'created_at' => now()->subMinute(),
    ]);

    Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-FRESH',
        'subject' => 'Fresh conversation',
        'status' => 'open',
    ]);

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Attention')
        ->assertSeeInOrder(['Visitor latest', 'Needs reply'])
        ->assertSeeInOrder(['Agent latest', 'Waiting on visitor'])
        ->assertSeeInOrder(['Fresh conversation', 'Needs reply']);
});

test('dashboard filters conversations by attention state', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);

    $needsReplyConversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-NEEDS2',
        'subject' => 'Visitor needs a hand',
        'status' => 'open',
        'last_message_at' => now()->subMinutes(2),
    ]);
    ConversationMessage::factory()->for($needsReplyConversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'Can someone help?',
        'created_at' => now()->subMinutes(2),
    ]);

    $waitingConversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-WAIT2',
        'subject' => 'Agent is waiting',
        'status' => 'open',
        'last_message_at' => now()->subMinute(),
    ]);
    ConversationMessage::factory()->for($waitingConversation)->create([
        'sender_type' => User::class,
        'sender_id' => $agent->id,
        'body' => 'Can you try again?',
        'created_at' => now()->subMinute(),
    ]);

    $this->actingAs($agent)
        ->get('/dashboard?conversation_filter=needs_reply')
        ->assertOk()
        ->assertSee('Needs reply')
        ->assertSee('Visitor needs a hand')
        ->assertDontSee('Agent is waiting');
});

test('dashboard filters conversations by assignment state', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $otherAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);

    Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $agent->id,
        'support_code' => 'WF-MINE',
        'subject' => 'Mine to answer',
        'status' => 'open',
    ]);

    Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => null,
        'support_code' => 'WF-OPEN',
        'subject' => 'Ready to claim',
        'status' => 'open',
    ]);

    Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $otherAgent->id,
        'support_code' => 'WF-THEIRS',
        'subject' => 'Assigned elsewhere',
        'status' => 'open',
    ]);

    $this->actingAs($agent)
        ->get('/dashboard?conversation_filter=assigned_to_me')
        ->assertOk()
        ->assertSee('Assigned to me')
        ->assertSee('Mine to answer')
        ->assertDontSee('Ready to claim')
        ->assertDontSee('Assigned elsewhere');

    $this->actingAs($agent)
        ->get('/dashboard?conversation_filter=unassigned')
        ->assertOk()
        ->assertSee('Unassigned')
        ->assertSee('Ready to claim')
        ->assertDontSee('Mine to answer')
        ->assertDontSee('Assigned elsewhere');
});

test('dashboard exposes conversation queue filter links', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('All open')
        ->assertSee('Needs reply')
        ->assertSee('Assigned to me')
        ->assertSee('Unassigned')
        ->assertSee('/dashboard?conversation_filter=needs_reply', false)
        ->assertSee('/dashboard?conversation_filter=assigned_to_me', false)
        ->assertSee('/dashboard?conversation_filter=unassigned', false);
});

test('dashboard lists open tickets for the agent account', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $otherAccount = Account::factory()->create(['name' => 'Other Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);

    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-TICKETDB',
        'subject' => 'Checkout trouble',
        'status' => 'open',
        'last_message_at' => now()->subMinute(),
    ]);

    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($conversation)
        ->for($visitor, 'requester')
        ->for($agent, 'assignee')
        ->create([
            'subject' => 'Escalated checkout issue',
            'priority' => 'high',
            'status' => 'open',
        ]);

    Ticket::factory()
        ->for($account)
        ->for($site)
        ->create([
            'subject' => 'Closed account issue',
            'status' => 'closed',
        ]);

    $otherSite = Site::factory()->for($otherAccount)->create(['name' => 'Other Docs']);
    Ticket::factory()
        ->for($otherAccount)
        ->for($otherSite)
        ->create([
            'subject' => 'Other account issue',
            'status' => 'open',
        ]);

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Tickets')
        ->assertSee('1 open')
        ->assertSee('Escalated checkout issue')
        ->assertSee("/dashboard/tickets/{$ticket->id}", false)
        ->assertSee('Acme Docs')
        ->assertSee('High')
        ->assertSee('WF-TICKETDB')
        ->assertDontSee('Closed account issue')
        ->assertDontSee('Other account issue')
        ->assertDontSee('Other Docs');
});

test('dashboard filters tickets by assignee state', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $otherAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);

    Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($agent, 'assignee')
        ->create([
            'subject' => 'Mine to resolve',
            'status' => 'open',
        ]);

    Ticket::factory()
        ->for($account)
        ->for($site)
        ->create([
            'subject' => 'Ready for an owner',
            'status' => 'open',
        ]);

    Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($otherAgent, 'assignee')
        ->create([
            'subject' => 'Someone else is handling it',
            'status' => 'open',
        ]);

    $this->actingAs($agent)
        ->get('/dashboard?ticket_filter=assigned_to_me')
        ->assertOk()
        ->assertSee('Assigned to me')
        ->assertSee('Mine to resolve')
        ->assertDontSee('Ready for an owner')
        ->assertDontSee('Someone else is handling it');

    $this->actingAs($agent)
        ->get('/dashboard?ticket_filter=unassigned')
        ->assertOk()
        ->assertSee('Unassigned')
        ->assertSee('Ready for an owner')
        ->assertDontSee('Mine to resolve')
        ->assertDontSee('Someone else is handling it');
});

test('dashboard exposes ticket queue filter links', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('All open')
        ->assertSee('Assigned to me')
        ->assertSee('Unassigned')
        ->assertSee('/dashboard?ticket_filter=assigned_to_me', false)
        ->assertSee('/dashboard?ticket_filter=unassigned', false);
});

test('dashboard lists account agents with active workload counts', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $otherAccount = Account::factory()->create(['name' => 'Other Support']);
    $agent = User::factory()->for($account)->create([
        'email' => 'ada@example.test',
        'name' => 'Ada Agent',
    ]);
    $teammate = User::factory()->for($account)->create([
        'email' => 'bea@example.test',
        'name' => 'Bea Builder',
    ]);
    $otherAgent = User::factory()->for($otherAccount)->create([
        'email' => 'otto@example.test',
        'name' => 'Otto Outside',
    ]);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create();
    $otherSite = Site::factory()->for($otherAccount)->create(['name' => 'Other Docs']);
    $otherVisitor = Visitor::factory()->for($otherSite)->create();

    Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $agent->id,
        'status' => 'open',
    ]);
    Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $agent->id,
        'status' => 'closed',
    ]);
    Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($agent, 'assignee')
        ->create(['status' => 'open']);
    Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($agent, 'assignee')
        ->create(['status' => 'closed']);
    Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($teammate, 'assignee')
        ->count(2)
        ->create(['status' => 'open']);
    Conversation::factory()->for($otherSite)->for($otherVisitor)->create([
        'assigned_agent_id' => $otherAgent->id,
        'status' => 'open',
    ]);
    Ticket::factory()
        ->for($otherAccount)
        ->for($otherSite)
        ->for($otherAgent, 'assignee')
        ->create(['status' => 'open']);

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Team')
        ->assertSee('Open conversations')
        ->assertSee('Open tickets')
        ->assertSeeInOrder(['Ada Agent', 'ada@example.test', '1', '1'])
        ->assertSeeInOrder(['Bea Builder', 'bea@example.test', '0', '2'])
        ->assertDontSee('Otto Outside')
        ->assertDontSee('otto@example.test');
});

test('dashboard shows ready realtime status when reverb is configured', function (): void {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'reverb-key');
    config()->set('broadcasting.connections.reverb.secret', 'reverb-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'reverb-app');
    config()->set('broadcasting.connections.reverb.options.host', 'wayfindr.test');
    config()->set('broadcasting.connections.reverb.options.port', 443);
    config()->set('broadcasting.connections.reverb.options.scheme', 'https');

    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Realtime')
        ->assertSee('Ready')
        ->assertSee('Reverb broadcasts are configured.')
        ->assertSee('Broadcast driver')
        ->assertSee('reverb')
        ->assertSee('wayfindr.test:443')
        ->assertSee('https')
        ->assertSee('App ID')
        ->assertSee('Secret')
        ->assertSee('Set');
});

test('dashboard shows realtime setup guidance when reverb is incomplete', function (): void {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', null);
    config()->set('broadcasting.connections.reverb.secret', null);
    config()->set('broadcasting.connections.reverb.app_id', 'reverb-app');
    config()->set('broadcasting.connections.reverb.options.host', null);
    config()->set('broadcasting.connections.reverb.options.port', 443);
    config()->set('broadcasting.connections.reverb.options.scheme', 'https');

    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Realtime')
        ->assertSee('Needs setup')
        ->assertSee('Add Reverb app credentials and public host settings before enabling live updates.')
        ->assertSee('App key')
        ->assertSee('Missing')
        ->assertSee('App ID')
        ->assertSee('Set')
        ->assertSee('Secret')
        ->assertSee('Missing')
        ->assertSee('Endpoint')
        ->assertSee('Incomplete');
});

test('dashboard shows realtime disabled when broadcasting is not using reverb', function (): void {
    config()->set('broadcasting.default', 'log');

    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Realtime')
        ->assertSee('Disabled')
        ->assertSee('Set BROADCAST_CONNECTION=reverb to deliver live updates.')
        ->assertSee('Broadcast driver')
        ->assertSee('log');
});

test('dashboard reminds operators to respect retained visitor data', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Data responsibility')
        ->assertSee('Retaining visitor-supplied data may create privacy, security, and legal obligations.')
        ->assertSee('Keep only what you need, set a retention period you can justify, and make sure your privacy notice matches how this Wayfindr installation is used.');
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

test('agent can close an open conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-CLOSE1',
        'subject' => 'Checkout trouble',
        'status' => 'open',
        'closed_at' => null,
    ]);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-CLOSE1')
        ->assertOk()
        ->assertSee('Close conversation');

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-CLOSE1')
        ->post('/dashboard/conversations/WF-CLOSE1/close')
        ->assertRedirect('/dashboard/conversations/WF-CLOSE1')
        ->assertSessionHas('status', 'Conversation closed.');

    $conversation->refresh();

    expect($conversation->status)->toBe('closed')
        ->and($conversation->closed_at)->not->toBeNull();

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Checkout trouble');
});

test('agent can reopen a closed conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-REOPEN1',
        'subject' => 'Checkout trouble',
        'status' => 'closed',
        'closed_at' => now()->subMinute(),
    ]);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-REOPEN1')
        ->assertOk()
        ->assertSee('Reopen conversation');

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-REOPEN1')
        ->post('/dashboard/conversations/WF-REOPEN1/reopen')
        ->assertRedirect('/dashboard/conversations/WF-REOPEN1')
        ->assertSessionHas('status', 'Conversation reopened.');

    $conversation->refresh();

    expect($conversation->status)->toBe('open')
        ->and($conversation->closed_at)->toBeNull();
});

test('agent reply reopens a closed conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-REPLYOP',
        'subject' => 'Checkout trouble',
        'status' => 'closed',
        'closed_at' => now()->subMinute(),
    ]);

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-REPLYOP')
        ->post('/dashboard/conversations/WF-REPLYOP/messages', [
            'body' => 'I can keep helping here.',
        ])
        ->assertRedirect('/dashboard/conversations/WF-REPLYOP')
        ->assertSessionHas('status', 'Reply sent.');

    $conversation->refresh();

    expect($conversation->status)->toBe('open')
        ->and($conversation->closed_at)->toBeNull()
        ->and($conversation->last_message_at)->not->toBeNull();
});

test('agent can claim an unassigned conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => null,
        'support_code' => 'WF-CLAIM1',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-CLAIM1')
        ->assertOk()
        ->assertSee('Assigned to')
        ->assertSee('Unassigned')
        ->assertSee('Claim conversation');

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-CLAIM1')
        ->post('/dashboard/conversations/WF-CLAIM1/claim')
        ->assertRedirect('/dashboard/conversations/WF-CLAIM1')
        ->assertSessionHas('status', 'Conversation claimed.');

    expect($conversation->fresh()->assigned_agent_id)->toBe($agent->id);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-CLAIM1')
        ->assertOk()
        ->assertSee('Ada Agent')
        ->assertSee('Release conversation');
});

test('assigned agent can release a conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $agent->id,
        'support_code' => 'WF-RELEASE1',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-RELEASE1')
        ->post('/dashboard/conversations/WF-RELEASE1/release')
        ->assertRedirect('/dashboard/conversations/WF-RELEASE1')
        ->assertSessionHas('status', 'Conversation released.');

    expect($conversation->fresh()->assigned_agent_id)->toBeNull();
});

test('agent cannot release another agents conversation assignment', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $assignedAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $assignedAgent->id,
        'support_code' => 'WF-RELEASE2',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);

    $this->actingAs($agent)
        ->post('/dashboard/conversations/WF-RELEASE2/release')
        ->assertForbidden();

    expect($conversation->fresh()->assigned_agent_id)->toBe($assignedAgent->id);
});

test('agent reply claims an unassigned conversation without stealing assigned conversations', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $assignedAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $unassignedConversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => null,
        'support_code' => 'WF-AUTOSIGN',
        'subject' => 'Unassigned checkout trouble',
        'status' => 'open',
    ]);
    $assignedConversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $assignedAgent->id,
        'support_code' => 'WF-NOSTEAL',
        'subject' => 'Assigned checkout trouble',
        'status' => 'open',
    ]);

    $this->actingAs($agent)
        ->post('/dashboard/conversations/WF-AUTOSIGN/messages', [
            'body' => 'I can help with this.',
        ])
        ->assertRedirect('/dashboard/conversations/WF-AUTOSIGN');

    $this->actingAs($agent)
        ->post('/dashboard/conversations/WF-NOSTEAL/messages', [
            'body' => 'Adding a note without taking ownership.',
        ])
        ->assertRedirect('/dashboard/conversations/WF-NOSTEAL');

    expect($unassignedConversation->fresh()->assigned_agent_id)->toBe($agent->id)
        ->and($assignedConversation->fresh()->assigned_agent_id)->toBe($assignedAgent->id);
});

test('message direction changes the conversation attention state', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create([
        'name' => 'Acme Docs',
        'public_key' => 'site_public_docs',
    ]);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-ATTEND1',
        'subject' => 'Checkout trouble',
        'status' => 'open',
        'last_message_at' => now()->subMinute(),
    ]);

    ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'I am stuck.',
        'created_at' => now()->subMinute(),
    ]);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-ATTEND1')
        ->assertOk()
        ->assertSee('Attention')
        ->assertSee('Needs reply');

    $this->actingAs($agent)
        ->post('/dashboard/conversations/WF-ATTEND1/messages', [
            'body' => 'Can you try that again?',
        ])
        ->assertRedirect('/dashboard/conversations/WF-ATTEND1');

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-ATTEND1')
        ->assertOk()
        ->assertSee('Waiting on visitor');

    $token = $this->postJson('/api/widget/bootstrap', [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-acme',
        'page_url' => 'https://docs.example.test/install',
    ])
        ->assertSuccessful()
        ->json('data.visitor.token');

    $this->postJson('/api/conversations/WF-ATTEND1/messages', [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-acme',
        'visitor_token' => $token,
        'body' => 'Still seeing the same issue.',
    ])->assertCreated();

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-ATTEND1')
        ->assertOk()
        ->assertSee('Needs reply');
});

test('agent cannot close another account conversation', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $otherSite = Site::factory()->for($otherAccount)->create();
    $otherVisitor = Visitor::factory()->for($otherSite)->create();

    Conversation::factory()->for($otherSite)->for($otherVisitor)->create([
        'support_code' => 'WF-OTHERCL',
        'status' => 'open',
    ]);

    $this->actingAs($agent)
        ->post('/dashboard/conversations/WF-OTHERCL/close')
        ->assertNotFound();
});

test('agent can create a ticket from their account conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-TICKET1',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);

    ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'The checkout button is stuck.',
        'created_at' => now()->subMinutes(2),
    ]);

    ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => User::class,
        'sender_id' => $agent->id,
        'body' => 'I can help with that.',
        'created_at' => now()->subMinute(),
    ]);

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-TICKET1')
        ->post('/dashboard/conversations/WF-TICKET1/tickets', [
            'priority' => 'high',
        ])
        ->assertRedirect('/dashboard/conversations/WF-TICKET1')
        ->assertSessionHas('status', 'Ticket created.');

    $this->assertDatabaseHas('tickets', [
        'account_id' => $account->id,
        'site_id' => $site->id,
        'conversation_id' => $conversation->id,
        'requester_id' => $visitor->id,
        'assignee_id' => $agent->id,
        'status' => 'open',
        'priority' => 'high',
        'subject' => 'Checkout trouble',
    ]);

    $ticket = Ticket::query()->sole();

    expect($ticket->description)
        ->toContain('Visitor: The checkout button is stuck.')
        ->toContain('Ada Agent: I can help with that.');

    expect($ticket->metadata)->toMatchArray([
        'source' => 'conversation',
        'support_code' => 'WF-TICKET1',
    ]);

    expect($conversation->fresh()->assigned_agent_id)->toBe($agent->id);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-TICKET1')
        ->assertOk()
        ->assertSee('Ticket created.')
        ->assertSee('Ticket')
        ->assertSee('Checkout trouble')
        ->assertSee("/dashboard/tickets/{$ticket->id}", false)
        ->assertSee('High')
        ->assertSee('Open');
});

test('agent can view a durable ticket record for their account', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $assignee = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-TICKETSHOW',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);
    ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'The checkout button is still stuck.',
    ]);
    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($conversation)
        ->for($visitor, 'requester')
        ->for($assignee, 'assignee')
        ->create([
            'description' => 'The visitor cannot finish checkout after entering shipping details.',
            'priority' => 'high',
            'status' => 'open',
            'subject' => 'Escalated checkout issue',
        ]);

    $this->actingAs($agent)
        ->get("/dashboard/tickets/{$ticket->id}")
        ->assertOk()
        ->assertSee('Back to dashboard')
        ->assertSee('Escalated checkout issue')
        ->assertSee('Open')
        ->assertSee('High')
        ->assertSee('Acme Docs')
        ->assertSee('anon-acme')
        ->assertSee('WF-TICKETSHOW')
        ->assertSee('Checkout trouble')
        ->assertSee('The visitor cannot finish checkout after entering shipping details.')
        ->assertSee('Bea Builder')
        ->assertSee('Assign ticket')
        ->assertSee('Close ticket')
        ->assertSee('/dashboard/conversations/WF-TICKETSHOW', false);
});

test('agent can close a ticket from its detail page', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-DETAILCLOSE',
        'subject' => 'Checkout trouble',
    ]);
    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($conversation)
        ->for($visitor, 'requester')
        ->for($agent, 'assignee')
        ->create([
            'subject' => 'Escalated checkout issue',
            'status' => 'open',
            'closed_at' => null,
        ]);

    $this->actingAs($agent)
        ->from("/dashboard/tickets/{$ticket->id}")
        ->post("/dashboard/tickets/{$ticket->id}/close")
        ->assertRedirect("/dashboard/tickets/{$ticket->id}")
        ->assertSessionHas('status', 'Ticket closed.');

    expect($ticket->fresh())
        ->status->toBe('closed')
        ->closed_at->not->toBeNull();
});

test('agent cannot view another account ticket record', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $otherSite = Site::factory()->for($otherAccount)->create();
    $otherTicket = Ticket::factory()
        ->for($otherAccount)
        ->for($otherSite)
        ->create();

    $this->actingAs($agent)
        ->get("/dashboard/tickets/{$otherTicket->id}")
        ->assertNotFound();
});

test('creating a ticket from a conversation is idempotent', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $site = Site::factory()->for($account)->create();
    $visitor = Visitor::factory()->for($site)->create();
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-TICKET2',
        'subject' => 'Existing handoff',
    ]);

    Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($conversation)
        ->for($visitor, 'requester')
        ->for($agent, 'assignee')
        ->create([
            'subject' => 'Existing handoff',
        ]);

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-TICKET2')
        ->post('/dashboard/conversations/WF-TICKET2/tickets', [
            'priority' => 'urgent',
        ])
        ->assertRedirect('/dashboard/conversations/WF-TICKET2')
        ->assertSessionHas('status', 'Ticket already exists.');

    $this->assertDatabaseCount('tickets', 1);
});

test('agent can close a linked ticket from their account conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-TICKETCLOSE',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);
    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($conversation)
        ->for($visitor, 'requester')
        ->for($agent, 'assignee')
        ->create([
            'subject' => 'Escalated checkout issue',
            'status' => 'open',
            'closed_at' => null,
        ]);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-TICKETCLOSE')
        ->assertOk()
        ->assertSee('Escalated checkout issue')
        ->assertSee('Close ticket');

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-TICKETCLOSE')
        ->post("/dashboard/tickets/{$ticket->id}/close")
        ->assertRedirect('/dashboard/conversations/WF-TICKETCLOSE')
        ->assertSessionHas('status', 'Ticket closed.');

    expect($ticket->fresh())
        ->status->toBe('closed')
        ->closed_at->not->toBeNull();

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Escalated checkout issue');

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-TICKETCLOSE')
        ->assertOk()
        ->assertSee('Closed')
        ->assertSee('Reopen ticket');
});

test('agent can reopen a linked ticket from their account conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-TICKETOPEN',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);
    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($conversation)
        ->for($visitor, 'requester')
        ->for($agent, 'assignee')
        ->create([
            'subject' => 'Escalated checkout issue',
            'status' => 'closed',
            'closed_at' => now()->subMinute(),
        ]);

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-TICKETOPEN')
        ->post("/dashboard/tickets/{$ticket->id}/reopen")
        ->assertRedirect('/dashboard/conversations/WF-TICKETOPEN')
        ->assertSessionHas('status', 'Ticket reopened.');

    expect($ticket->fresh())
        ->status->toBe('open')
        ->closed_at->toBeNull();

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Escalated checkout issue');
});

test('agent can reassign a linked ticket to another account agent', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $assignee = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-ASSIGNT1',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);
    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($conversation)
        ->for($visitor, 'requester')
        ->for($agent, 'assignee')
        ->create([
            'subject' => 'Escalated checkout issue',
            'status' => 'open',
        ]);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-ASSIGNT1')
        ->assertOk()
        ->assertSee('Assign ticket')
        ->assertSee('Ada Agent')
        ->assertSee('Bea Builder');

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-ASSIGNT1')
        ->put("/dashboard/tickets/{$ticket->id}/assignee", [
            'assignee_id' => $assignee->id,
        ])
        ->assertRedirect('/dashboard/conversations/WF-ASSIGNT1')
        ->assertSessionHas('status', 'Ticket assignee updated.');

    expect($ticket->fresh()->assignee_id)->toBe($assignee->id);

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Assignee')
        ->assertSee('Bea Builder');
});

test('agent can clear a linked ticket assignee', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-UNASSIGNT',
    ]);
    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($conversation)
        ->for($visitor, 'requester')
        ->for($agent, 'assignee')
        ->create(['status' => 'open']);

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-UNASSIGNT')
        ->put("/dashboard/tickets/{$ticket->id}/assignee", [
            'assignee_id' => '',
        ])
        ->assertRedirect('/dashboard/conversations/WF-UNASSIGNT')
        ->assertSessionHas('status', 'Ticket assignee updated.');

    expect($ticket->fresh()->assignee_id)->toBeNull();

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-UNASSIGNT')
        ->assertOk()
        ->assertSee('Unassigned');
});

test('agent cannot assign a ticket to another account agent', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $otherAgent = User::factory()->for($otherAccount)->create();
    $site = Site::factory()->for($account)->create();
    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($agent, 'assignee')
        ->create(['status' => 'open']);

    $this->actingAs($agent)
        ->from('/dashboard')
        ->put("/dashboard/tickets/{$ticket->id}/assignee", [
            'assignee_id' => $otherAgent->id,
        ])
        ->assertSessionHasErrors('assignee_id');

    expect($ticket->fresh()->assignee_id)->toBe($agent->id);
});

test('agent cannot close another account ticket', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $otherSite = Site::factory()->for($otherAccount)->create();
    $otherTicket = Ticket::factory()
        ->for($otherAccount)
        ->for($otherSite)
        ->create([
            'status' => 'open',
            'closed_at' => null,
        ]);

    $this->actingAs($agent)
        ->post("/dashboard/tickets/{$otherTicket->id}/close")
        ->assertNotFound();

    expect($otherTicket->fresh())
        ->status->toBe('open')
        ->closed_at->toBeNull();
});

test('agent cannot create a ticket for another account conversation', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $otherSite = Site::factory()->for($otherAccount)->create();
    $otherVisitor = Visitor::factory()->for($otherSite)->create();

    Conversation::factory()->for($otherSite)->for($otherVisitor)->create([
        'support_code' => 'WF-TICKET3',
    ]);

    $this->actingAs($agent)
        ->post('/dashboard/conversations/WF-TICKET3/tickets', [
            'priority' => 'high',
        ])
        ->assertNotFound();

    $this->assertDatabaseCount('tickets', 0);
});

test('agent conversation page exposes live cobrowse update readiness when reverb is configured', function (): void {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'reverb-key');
    config()->set('broadcasting.connections.reverb.secret', 'reverb-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'reverb-app');
    config()->set('broadcasting.connections.reverb.options.host', 'wayfindr.test');
    config()->set('broadcasting.connections.reverb.options.port', 443);
    config()->set('broadcasting.connections.reverb.options.scheme', 'https');

    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-LIVECOBROWSE',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);

    CobrowseSession::factory()->for($conversation)->for($site)->for($visitor)->create([
        'status' => 'granted',
        'consented_at' => now()->subMinute(),
        'ended_at' => null,
    ]);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-LIVECOBROWSE')
        ->assertOk()
        ->assertSee('Cobrowse updates')
        ->assertSee('Waiting for live cobrowse updates.')
        ->assertSee('data-cobrowse-update-status', false)
        ->assertSee('conversation.cobrowse.updated')
        ->assertSee('private-conversations.WF-LIVECOBROWSE')
        ->assertSee('"appKey":"reverb-key"', false)
        ->assertSee('"host":"wayfindr.test"', false);
});

test('agent can see cobrowse consent state on a conversation', function (?array $sessionAttributes, string $label, string $message): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-COBROWSE',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);

    if ($sessionAttributes) {
        CobrowseSession::factory()->for($conversation)->for($site)->for($visitor)->create($sessionAttributes);
    }

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-COBROWSE')
        ->assertOk()
        ->assertSee('Cobrowse')
        ->assertSee($label)
        ->assertSee($message);
})->with([
    'unavailable' => [
        null,
        'Unavailable',
        'Visitor has not granted cobrowse consent.',
    ],
    'pending' => [
        ['status' => 'requested', 'consented_at' => null, 'ended_at' => null],
        'Pending consent',
        'Waiting for visitor consent before cobrowsing can start.',
    ],
    'granted' => [
        ['status' => 'granted', 'consented_at' => now()->subMinute(), 'ended_at' => null],
        'Granted',
        'Visitor granted cobrowse consent.',
    ],
    'revoked' => [
        ['status' => 'revoked', 'consented_at' => now()->subMinutes(2), 'ended_at' => now()->subMinute()],
        'Revoked',
        'Visitor revoked cobrowse consent.',
    ],
    'ended' => [
        ['status' => 'ended', 'consented_at' => now()->subMinutes(2), 'ended_at' => now()->subMinute()],
        'Ended',
        'Cobrowse session ended.',
    ],
]);

test('agent can request cobrowse consent for their account conversation', function (): void {
    Event::fake([CobrowseStateUpdated::class]);

    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-REQUEST1',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-REQUEST1')
        ->post('/dashboard/conversations/WF-REQUEST1/cobrowse/request')
        ->assertRedirect('/dashboard/conversations/WF-REQUEST1')
        ->assertSessionHas('status', 'Cobrowse requested.');

    $this->assertDatabaseHas('cobrowse_sessions', [
        'conversation_id' => $conversation->id,
        'site_id' => $site->id,
        'visitor_id' => $visitor->id,
        'requested_by_id' => $agent->id,
        'status' => 'requested',
        'consented_at' => null,
        'ended_at' => null,
    ]);

    Event::assertDispatched(
        CobrowseStateUpdated::class,
        fn (CobrowseStateUpdated $event): bool => $event->cobrowseSession->conversation_id === $conversation->id
            && $event->kind === 'consent_requested'
    );

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-REQUEST1')
        ->assertOk()
        ->assertSee('Pending consent')
        ->assertSee('Waiting for visitor consent before cobrowsing can start.')
        ->assertSee('Cancel request');
});

test('agent cobrowse request is idempotent while a session is active', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-REQUEST2',
    ]);

    CobrowseSession::factory()->for($conversation)->for($site)->for($visitor)->create([
        'requested_by_id' => $agent->id,
        'status' => 'requested',
        'consented_at' => null,
        'ended_at' => null,
    ]);

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-REQUEST2')
        ->post('/dashboard/conversations/WF-REQUEST2/cobrowse/request')
        ->assertRedirect('/dashboard/conversations/WF-REQUEST2')
        ->assertSessionHas('status', 'Cobrowse request already active.');

    $this->assertDatabaseCount('cobrowse_sessions', 1);
});

test('agent can end an active cobrowse session', function (): void {
    Event::fake([CobrowseStateUpdated::class]);

    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-END1',
    ]);
    $session = CobrowseSession::factory()->for($conversation)->for($site)->for($visitor)->create([
        'requested_by_id' => $agent->id,
        'status' => 'granted',
        'consented_at' => now()->subMinute(),
        'ended_at' => null,
        'metadata' => [
            'page_state' => [
                'page_url' => 'https://docs.example.test/install',
                'title' => 'Install Guide',
            ],
        ],
    ]);

    $this->actingAs($agent)
        ->from('/dashboard/conversations/WF-END1')
        ->post('/dashboard/conversations/WF-END1/cobrowse/end')
        ->assertRedirect('/dashboard/conversations/WF-END1')
        ->assertSessionHas('status', 'Cobrowse session ended.');

    expect($session->fresh())
        ->status->toBe('ended')
        ->ended_at->not->toBeNull()
        ->metadata->toMatchArray([
            'ended_by_id' => $agent->id,
            'ended_by_type' => 'agent',
        ]);

    Event::assertDispatched(
        CobrowseStateUpdated::class,
        fn (CobrowseStateUpdated $event): bool => $event->cobrowseSession->id === $session->id
            && $event->kind === 'ended'
    );

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-END1')
        ->assertOk()
        ->assertSee('Ended')
        ->assertSee('Cobrowse session ended.')
        ->assertSee('Request cobrowse');
});

test('agent cannot request cobrowse for another account conversation', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $otherSite = Site::factory()->for($otherAccount)->create();
    $otherVisitor = Visitor::factory()->for($otherSite)->create();

    Conversation::factory()->for($otherSite)->for($otherVisitor)->create([
        'support_code' => 'WF-REQUEST3',
    ]);

    $this->actingAs($agent)
        ->post('/dashboard/conversations/WF-REQUEST3/cobrowse/request')
        ->assertNotFound();

    $this->assertDatabaseCount('cobrowse_sessions', 0);
});

test('agent can see cobrowse telemetry on a conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-TELEMETRY',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);

    CobrowseSession::factory()->for($conversation)->for($site)->for($visitor)->create([
        'status' => 'granted',
        'consented_at' => now()->subMinute(),
        'ended_at' => null,
        'metadata' => [
            'telemetry' => [
                'rtt_ms' => 184,
                'max_rtt_ms' => 240,
                'payload_bytes' => 8192,
                'max_payload_bytes' => 16384,
                'dropped_batches' => 2,
                'reconnects' => 1,
                'samples' => 5,
                'reported_at' => now()->toJSON(),
            ],
        ],
    ]);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-TELEMETRY')
        ->assertOk()
        ->assertSee('Connection telemetry')
        ->assertSee('RTT')
        ->assertSee('184 ms')
        ->assertSee('Max RTT')
        ->assertSee('240 ms')
        ->assertSee('Payload')
        ->assertSee('8,192 bytes')
        ->assertSee('Max payload')
        ->assertSee('16,384 bytes')
        ->assertSee('Dropped batches')
        ->assertSee('2')
        ->assertSee('Reconnects')
        ->assertSee('1')
        ->assertSee('Samples')
        ->assertSee('5');
});

test('agent can see cobrowse page state on a conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-PAGESTATE',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);

    CobrowseSession::factory()->for($conversation)->for($site)->for($visitor)->create([
        'status' => 'granted',
        'consented_at' => now()->subMinute(),
        'ended_at' => null,
        'metadata' => [
            'page_state' => [
                'page_url' => 'https://docs.example.test/install?step=2',
                'title' => 'Install Guide',
                'viewport_width' => 1366,
                'viewport_height' => 768,
                'scroll_x' => 0,
                'scroll_y' => 420,
                'visibility_state' => 'visible',
                'focused' => true,
                'reported_at' => now()->toJSON(),
            ],
        ],
    ]);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-PAGESTATE')
        ->assertOk()
        ->assertSee('Visitor page')
        ->assertSee('Install Guide')
        ->assertSee('https://docs.example.test/install?step=2')
        ->assertSee('1,366 x 768')
        ->assertSee('0, 420')
        ->assertSee('visible')
        ->assertSee('Focused');
});

test('agent can see a safe cobrowse snapshot preview on a conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-SNAPSHOT',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);

    CobrowseSession::factory()->for($conversation)->for($site)->for($visitor)->create([
        'status' => 'granted',
        'consented_at' => now()->subMinute(),
        'ended_at' => null,
        'metadata' => [
            'snapshot' => [
                'page_url' => 'https://docs.example.test/install?step=2',
                'title' => 'Install Guide',
                'html' => '<main><input value="super-secret-token"><p>Public checkout content.</p></main>',
                'text' => 'Public checkout content. [masked]',
                'node_count' => 8,
                'masked_count' => 2,
                'reported_at' => now()->toJSON(),
            ],
        ],
    ]);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-SNAPSHOT')
        ->assertOk()
        ->assertSee('Page snapshot')
        ->assertSee('Install Guide')
        ->assertSee('https://docs.example.test/install?step=2')
        ->assertSee('8 nodes')
        ->assertSee('2 masked')
        ->assertSee('Public checkout content. [masked]')
        ->assertDontSee('super-secret-token');
});

test('agent can see cobrowse mutation stream diagnostics on a conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-MUTATE',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);

    CobrowseSession::factory()->for($conversation)->for($site)->for($visitor)->create([
        'status' => 'granted',
        'consented_at' => now()->subMinute(),
        'ended_at' => null,
        'metadata' => [
            'mutations' => [
                'batch_count' => 3,
                'mutation_count' => 7,
                'dropped_count' => 2,
                'skipped_count' => 1,
                'last_sequence' => 42,
                'last_page_url' => 'https://docs.example.test/install?step=2',
                'last_reported_at' => now()->toJSON(),
                'recent_batches' => [
                    [
                        'sequence' => 42,
                        'mutation_count' => 2,
                        'dropped_count' => 0,
                        'skipped_count' => 1,
                        'page_url' => 'https://docs.example.test/install?step=2',
                        'reported_at' => now()->toJSON(),
                        'mutations' => [],
                    ],
                ],
            ],
        ],
    ]);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-MUTATE')
        ->assertOk()
        ->assertSee('Mutation stream')
        ->assertSee('3 batches')
        ->assertSee('7 mutations')
        ->assertSee('2 dropped')
        ->assertSee('1 skipped')
        ->assertSee('Sequence 42')
        ->assertSee('https://docs.example.test/install?step=2');
});

test('agent can see a sandboxed cobrowse replay preview on a conversation', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-acme']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-REPLAY',
        'subject' => 'Checkout trouble',
        'status' => 'open',
    ]);

    CobrowseSession::factory()->for($conversation)->for($site)->for($visitor)->create([
        'status' => 'granted',
        'consented_at' => now()->subMinute(),
        'ended_at' => null,
        'metadata' => [
            'snapshot' => [
                'page_url' => 'https://docs.example.test/install?step=2',
                'title' => 'Install Guide',
                'html' => '<main><h1>Install Guide</h1><p>Original public copy.</p><button aria-expanded="false">Details</button><script>window.secret="steal-token"</script></main>',
                'text' => 'Install Guide. [masked]',
                'node_count' => 6,
                'masked_count' => 1,
                'reported_at' => now()->subMinute()->toJSON(),
            ],
            'mutations' => [
                'batch_count' => 2,
                'mutation_count' => 3,
                'dropped_count' => 0,
                'skipped_count' => 0,
                'last_sequence' => 2,
                'last_page_url' => 'https://docs.example.test/install?step=2',
                'last_reported_at' => now()->toJSON(),
                'recent_batches' => [
                    [
                        'sequence' => 1,
                        'mutation_count' => 2,
                        'dropped_count' => 0,
                        'skipped_count' => 0,
                        'page_url' => 'https://docs.example.test/install?step=2',
                        'reported_at' => now()->toJSON(),
                        'mutations' => [
                            [
                                'type' => 'text',
                                'path' => 'body:nth-of-type(1) > main:nth-of-type(1) > p:nth-of-type(1)',
                                'text' => 'Updated public copy.',
                            ],
                            [
                                'type' => 'attribute',
                                'path' => 'body:nth-of-type(1) > main:nth-of-type(1) > button:nth-of-type(1)',
                                'attribute_name' => 'aria-expanded',
                                'attribute_value' => 'true',
                            ],
                        ],
                    ],
                    [
                        'sequence' => 2,
                        'mutation_count' => 1,
                        'dropped_count' => 0,
                        'skipped_count' => 0,
                        'page_url' => 'https://docs.example.test/install?step=2',
                        'reported_at' => now()->toJSON(),
                        'mutations' => [
                            [
                                'type' => 'added',
                                'path' => 'body:nth-of-type(1) > main:nth-of-type(1)',
                                'html' => '<p>Fresh public hint.</p><script>window.secret="mutation-token"</script>',
                                'text' => 'Fresh public hint.',
                                'node_count' => 1,
                                'masked_count' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-REPLAY')
        ->assertOk()
        ->assertSee('Replay preview')
        ->assertSee('sandbox', false)
        ->assertSee('srcdoc=', false)
        ->assertSee('Updated public copy.')
        ->assertSee('Fresh public hint.')
        ->assertSee('aria-expanded=&quot;true&quot;', false)
        ->assertSee('3 applied')
        ->assertSee('0 skipped')
        ->assertDontSee('steal-token')
        ->assertDontSee('mutation-token');
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
