<?php

namespace App\Support;

final class CobrowsePayloadBudget
{
    public const SNAPSHOT_HTML_MAX_CHARACTERS = 65535;

    public const SNAPSHOT_TEXT_MAX_CHARACTERS = 10000;

    public const MUTATION_BATCH_MAX_ITEMS = 50;

    public const MUTATION_TEXT_MAX_CHARACTERS = 5000;

    public const MUTATION_HTML_MAX_CHARACTERS = 10000;

    public const MUTATION_ATTRIBUTE_VALUE_MAX_CHARACTERS = 2048;

    public const MUTATION_RECENT_BATCHES_RETAINED = 20;

    public const TELEMETRY_PAYLOAD_MAX_BYTES = 10485760;

    /**
     * @return array<string, int>
     */
    public static function limits(): array
    {
        return [
            'snapshot_html_max_characters' => self::SNAPSHOT_HTML_MAX_CHARACTERS,
            'snapshot_text_max_characters' => self::SNAPSHOT_TEXT_MAX_CHARACTERS,
            'mutation_batch_max_items' => self::MUTATION_BATCH_MAX_ITEMS,
            'mutation_text_max_characters' => self::MUTATION_TEXT_MAX_CHARACTERS,
            'mutation_html_max_characters' => self::MUTATION_HTML_MAX_CHARACTERS,
            'mutation_attribute_value_max_characters' => self::MUTATION_ATTRIBUTE_VALUE_MAX_CHARACTERS,
            'mutation_recent_batches_retained' => self::MUTATION_RECENT_BATCHES_RETAINED,
            'telemetry_payload_max_bytes' => self::TELEMETRY_PAYLOAD_MAX_BYTES,
        ];
    }
}
