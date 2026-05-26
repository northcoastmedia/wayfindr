<?php

namespace App\Support;

final class TicketPriority
{
    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function options(): array
    {
        return [
            'low' => [
                'label' => 'Low',
                'description' => 'Nice-to-have follow-up or non-blocking question.',
            ],
            'normal' => [
                'label' => 'Normal',
                'description' => 'Standard support request with no immediate deadline.',
            ],
            'high' => [
                'label' => 'High',
                'description' => 'Time-sensitive issue affecting an important customer workflow.',
            ],
            'urgent' => [
                'label' => 'Urgent',
                'description' => 'Business-critical, active outage, or blocked production work.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(self::options());
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function guidanceOptions(): array
    {
        $options = self::options();

        return [
            'urgent' => $options['urgent'],
            'high' => $options['high'],
            'normal' => $options['normal'],
            'low' => $options['low'],
        ];
    }

    public static function label(string $priority): string
    {
        return self::options()[$priority]['label'] ?? ucfirst($priority);
    }
}
