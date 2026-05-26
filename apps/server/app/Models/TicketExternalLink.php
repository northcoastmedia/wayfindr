<?php

namespace App\Models;

use App\Support\ExternalIssueProvider;
use App\Support\ExternalIssueSyncStatus;
use Database\Factories\TicketExternalLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'account_id',
    'site_id',
    'ticket_id',
    'provider',
    'project_key',
    'external_id',
    'external_key',
    'url',
    'sync_status',
    'last_synced_at',
    'metadata',
])]
class TicketExternalLink extends Model
{
    /** @use HasFactory<TicketExternalLinkFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
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

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function providerLabel(): string
    {
        return ExternalIssueProvider::label($this->provider);
    }

    public function syncStatusLabel(): string
    {
        return ExternalIssueSyncStatus::label($this->sync_status);
    }
}
