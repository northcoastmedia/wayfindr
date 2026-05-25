<?php

namespace App\Models;

use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'account_id',
    'site_id',
    'conversation_id',
    'requester_id',
    'assignee_id',
    'status',
    'priority',
    'subject',
    'description',
    'metadata',
    'closed_at',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Visitor::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function attentionState(): string
    {
        if ($this->status === 'closed') {
            return 'resolved';
        }

        if ($this->status === 'pending') {
            return 'waiting_on_customer';
        }

        if (! $this->assignee_id) {
            return 'needs_owner';
        }

        $latestMessage = $this->conversation?->relationLoaded('latestMessage')
            ? $this->conversation->latestMessage
            : $this->conversation?->messages()
                ->latest('created_at')
                ->latest('id')
                ->first();

        if ($latestMessage) {
            return $latestMessage->sender_type === User::class
                ? 'waiting_on_customer'
                : 'needs_reply';
        }

        return 'needs_agent';
    }

    public function attentionLabel(): string
    {
        return match ($this->attentionState()) {
            'needs_reply' => 'Needs reply',
            'needs_owner' => 'Needs owner',
            'waiting_on_customer' => 'Waiting on customer',
            'resolved' => 'Resolved',
            default => 'Needs agent',
        };
    }

    public function attentionDescription(): string
    {
        return match ($this->attentionState()) {
            'needs_reply' => 'Visitor replied last.',
            'needs_owner' => 'Assign this ticket to keep it moving.',
            'waiting_on_customer' => $this->status === 'pending' ? 'Marked pending.' : 'Agent replied last.',
            'resolved' => 'Ticket is closed.',
            default => 'Ready for an agent update.',
        };
    }

    public function attentionSortRank(): int
    {
        return match ($this->attentionState()) {
            'needs_reply' => 10,
            'needs_owner' => 20,
            'needs_agent' => 30,
            'waiting_on_customer' => 70,
            'resolved' => 90,
            default => 50,
        };
    }

    public function auditEvents(): MorphMany
    {
        return $this->morphMany(AuditEvent::class, 'subject');
    }
}
