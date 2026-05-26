<?php

namespace App\Models;

use Database\Factories\SiteExternalIssueProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'account_id',
    'site_id',
    'external_issue_provider_connection_id',
    'project_key',
    'project_name',
    'web_url',
    'settings',
])]
class SiteExternalIssueProject extends Model
{
    /** @use HasFactory<SiteExternalIssueProjectFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'settings' => 'array',
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

    public function providerConnection(): BelongsTo
    {
        return $this->belongsTo(ExternalIssueProviderConnection::class, 'external_issue_provider_connection_id');
    }

    public function providerLabel(): string
    {
        return $this->providerConnection?->providerLabel() ?? 'External tracker';
    }

    public function hasCapability(string $capability): bool
    {
        return $this->providerConnection?->hasCapability($capability) ?? false;
    }

    /**
     * @return list<string>
     */
    public function capabilityLabels(): array
    {
        return $this->providerConnection?->capabilityLabels() ?? [];
    }
}
