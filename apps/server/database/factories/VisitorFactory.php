<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'external_id' => null,
            'anonymous_id' => 'anon_'.Str::lower((string) Str::ulid()),
            'name' => null,
            'email' => null,
            'metadata' => [],
            'last_seen_at' => now(),
        ];
    }
}
