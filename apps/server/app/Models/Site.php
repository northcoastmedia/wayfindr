<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['account_id', 'name', 'domain', 'public_key', 'settings'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
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

    public function supportAgents(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function eligibleSupportAgents(): BelongsToMany
    {
        return $this->supportAgents()
            ->where('users.account_id', $this->account_id);
    }

    public function hasExplicitSupportAgents(): bool
    {
        return $this->eligibleSupportAgents()->exists();
    }

    public function supportsAgent(User $agent): bool
    {
        if (! $agent->account_id || (int) $agent->account_id !== (int) $this->account_id) {
            return false;
        }

        if (! $this->hasExplicitSupportAgents()) {
            return true;
        }

        return $this->eligibleSupportAgents()->whereKey($agent->id)->exists();
    }

    /**
     * @return Builder<Site>
     */
    public function scopeVisibleToAgent(Builder $query, User $agent): Builder
    {
        return $query
            ->where('account_id', $agent->account_id)
            ->where(function (Builder $query) use ($agent): void {
                $query
                    ->whereDoesntHave('supportAgents', fn (Builder $query) => $query->where('users.account_id', $agent->account_id))
                    ->orWhereHas('supportAgents', fn (Builder $query) => $query
                        ->where('users.account_id', $agent->account_id)
                        ->whereKey($agent->id));
            });
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    public function latestVisitor(): HasOne
    {
        return $this->hasOne(Visitor::class)->latestOfMany('last_seen_at');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function cobrowseSessions(): HasMany
    {
        return $this->hasMany(CobrowseSession::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }
}
