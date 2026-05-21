<?php

namespace Tests\Feature;

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
use Tests\TestCase;

class SupportDomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_domain_tables_are_migrated(): void
    {
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
            $this->assertTrue(Schema::hasTable($table), "Expected [{$table}] table to exist.");
        }

        $this->assertTrue(Schema::hasColumn('users', 'account_id'));
    }

    public function test_support_session_records_share_the_expected_relationships(): void
    {
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

        $this->assertTrue($account->sites->contains($site));
        $this->assertTrue($account->agents->contains($agent));
        $this->assertTrue($site->visitors->contains($visitor));
        $this->assertTrue($visitor->conversations->contains($conversation));
        $this->assertTrue($conversation->messages->contains($message));
        $this->assertTrue($conversation->tickets->contains($ticket));
        $this->assertTrue($conversation->cobrowseSessions->contains($cobrowseSession));
        $this->assertTrue($message->sender->is($agent));
        $this->assertTrue($ticket->requester->is($visitor));
        $this->assertTrue($ticket->assignee->is($agent));
        $this->assertTrue($cobrowseSession->requestedBy->is($agent));
        $this->assertTrue($auditEvent->actor->is($agent));
        $this->assertTrue($auditEvent->subject->is($conversation));
    }

    public function test_factories_create_internally_consistent_domain_records(): void
    {
        $conversation = Conversation::factory()->create();
        $ticket = Ticket::factory()->create();
        $cobrowseSession = CobrowseSession::factory()->create();

        $this->assertSame($conversation->site_id, $conversation->visitor->site_id);
        $this->assertSame($ticket->account_id, $ticket->site->account_id);
        $this->assertSame($cobrowseSession->conversation->site_id, $cobrowseSession->site_id);
        $this->assertSame($cobrowseSession->conversation->visitor_id, $cobrowseSession->visitor_id);
    }
}
