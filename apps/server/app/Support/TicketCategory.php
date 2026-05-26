<?php

namespace App\Support;

final class TicketCategory
{
    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function options(): array
    {
        return [
            'question' => [
                'label' => 'Question',
                'description' => 'General question or how-to help.',
            ],
            'bug' => [
                'label' => 'Bug',
                'description' => 'Something broken or not working as expected.',
            ],
            'billing' => [
                'label' => 'Billing',
                'description' => 'Pricing, invoice, payment, or account billing issue.',
            ],
            'access' => [
                'label' => 'Access',
                'description' => 'Login, permissions, or account access issue.',
            ],
            'task' => [
                'label' => 'Task',
                'description' => 'Follow-up work, configuration, or operational request.',
            ],
            'other' => [
                'label' => 'Other',
                'description' => 'Anything that does not fit the other categories.',
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

    public static function label(?string $category): string
    {
        if (! $category) {
            return 'Uncategorized';
        }

        return self::options()[$category]['label'] ?? ucfirst($category);
    }
}
