<?php

namespace App\Models;

use App\Support\ExternalIssueCapability;
use App\Support\ExternalIssueProvider;
use Database\Factories\ExternalIssueProviderConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'account_id',
    'provider',
    'name',
    'base_url',
    'credentials',
    'capabilities',
    'settings',
    'is_enabled',
    'last_checked_at',
])]
class ExternalIssueProviderConnection extends Model
{
    /** @use HasFactory<ExternalIssueProviderConnectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'capabilities' => 'array',
            'settings' => 'array',
            'is_enabled' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function siteProjects(): HasMany
    {
        return $this->hasMany(SiteExternalIssueProject::class);
    }

    public function providerLabel(): string
    {
        return ExternalIssueProvider::label($this->provider);
    }

    public function hasCapability(string $capability): bool
    {
        return (bool) data_get(ExternalIssueCapability::flags($this->capabilities), $capability, false);
    }

    /**
     * @return list<string>
     */
    public function capabilityLabels(): array
    {
        return ExternalIssueCapability::activeLabels($this->capabilities);
    }
}
