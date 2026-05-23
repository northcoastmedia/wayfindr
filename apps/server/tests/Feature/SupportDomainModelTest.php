<?php

use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\CobrowseSession;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Site;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('support domain tables are migrated', function (): void {
    foreach ([
        'accounts',
        'sites',
        'visitors',
        'conversations',
        'conversation_messages',
        'tickets',
        'cobrowse_sessions',
        'audit_events',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Expected [{$table}] table to exist.");
    }

    expect(Schema::hasColumn('users', 'account_id'))->toBeTrue();
});

test('support session records share the expected relationships', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $site = Site::factory()->for($account)->create();
    $visitor = Visitor::factory()->for($site)->create([
        'external_id' => 'customer-123',
        'email' => 'customer@example.com',
    ]);

    $conversation = Conversation::factory()
        ->for($site)
        ->for($visitor)
        ->for($agent, 'assignedAgent')
        ->create([
            'support_code' => 'WF-123456',
            'status' => 'open',
        ]);

    $message = ConversationMessage::factory()
        ->for($conversation)
        ->create([
            'sender_type' => User::class,
            'sender_id' => $agent->id,
            'body' => 'How can I help?',
        ]);

    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($conversation)
        ->for($visitor, 'requester')
        ->for($agent, 'assignee')
        ->create([
            'status' => 'open',
            'priority' => 'normal',
        ]);

    $cobrowseSession = CobrowseSession::factory()
        ->for($conversation)
        ->for($site)
        ->for($visitor)
        ->for($agent, 'requestedBy')
        ->create([
            'status' => 'requested',
        ]);

    $auditEvent = AuditEvent::factory()
        ->for($account)
        ->for($site)
        ->create([
            'actor_type' => User::class,
            'actor_id' => $agent->id,
            'subject_type' => Conversation::class,
            'subject_id' => $conversation->id,
            'action' => 'conversation.created',
        ]);

    expect($account->sites->contains($site))->toBeTrue()
        ->and($account->agents->contains($agent))->toBeTrue()
        ->and($site->visitors->contains($visitor))->toBeTrue()
        ->and($visitor->conversations->contains($conversation))->toBeTrue()
        ->and($conversation->messages->contains($message))->toBeTrue()
        ->and($conversation->tickets->contains($ticket))->toBeTrue()
        ->and($conversation->cobrowseSessions->contains($cobrowseSession))->toBeTrue()
        ->and($message->sender->is($agent))->toBeTrue()
        ->and($ticket->requester->is($visitor))->toBeTrue()
        ->and($ticket->assignee->is($agent))->toBeTrue()
        ->and($cobrowseSession->requestedBy->is($agent))->toBeTrue()
        ->and($auditEvent->actor->is($agent))->toBeTrue()
        ->and($auditEvent->subject->is($conversation))->toBeTrue();
});

test('factories create internally consistent domain records', function (): void {
    $conversation = Conversation::factory()->create();
    $ticket = Ticket::factory()->create();
    $cobrowseSession = CobrowseSession::factory()->create();

    expect($conversation->site_id)->toBe($conversation->visitor->site_id)
        ->and($ticket->account_id)->toBe($ticket->site->account_id)
        ->and($cobrowseSession->conversation->site_id)->toBe($cobrowseSession->site_id)
        ->and($cobrowseSession->conversation->visitor_id)->toBe($cobrowseSession->visitor_id);
});
