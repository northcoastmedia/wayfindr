<?php

namespace App\Support;

use App\Models\Site;
use App\Models\Visitor;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use JsonException;

class VisitorSessionToken
{
    public function issue(Site $site, Visitor $visitor): string
    {
        return Crypt::encryptString(json_encode([
            'site_id' => $site->id,
            'visitor_id' => $visitor->id,
            'anonymous_id' => $visitor->anonymous_id,
            'issued_at' => now()->toJSON(),
        ], JSON_THROW_ON_ERROR));
    }

    public function visitorFromRequest(Request $request, Site $site, string $anonymousId): Visitor
    {
        $token = $this->tokenFromRequest($request);

        abort_if(! $token, 401, 'Visitor token is required.');

        $payload = $this->decode($token);

        abort_if((int) ($payload['site_id'] ?? 0) !== $site->id, 403, 'Visitor token does not match this site.');
        abort_if(! hash_equals((string) ($payload['anonymous_id'] ?? ''), $anonymousId), 403, 'Visitor token does not match this visitor.');

        $visitor = Visitor::query()
            ->whereKey((int) ($payload['visitor_id'] ?? 0))
            ->where('site_id', $site->id)
            ->where('anonymous_id', $anonymousId)
            ->first();

        abort_unless($visitor, 401, 'Visitor token is invalid.');

        return $visitor;
    }

    /**
     * @return array{site_id?: int, visitor_id?: int, anonymous_id?: string, issued_at?: string}
     */
    private function decode(string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            abort(401, 'Visitor token is invalid.');
        }

        abort_unless(is_array($payload), 401, 'Visitor token is invalid.');

        return $payload;
    }

    private function tokenFromRequest(Request $request): ?string
    {
        return $request->bearerToken()
            ?: $request->input('visitor_token')
            ?: $request->query('visitor_token');
    }
}
