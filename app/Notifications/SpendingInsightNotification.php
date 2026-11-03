<?php

namespace App\Notifications;

/**
 * "You spent 18% less than last week. Great job!"
 */
class SpendingInsightNotification extends BaseAppNotification
{
    public function __construct(
        protected int $percent,
        protected bool $spentLess,
    ) {
    }

    public function type(): string
    {
        return 'spending_insight';
    }

    public function title(): string
    {
        return $this->spentLess
            ? "You spent {$this->percent}% less than last week. Great job!"
            : "You spent {$this->percent}% more than last week. Worth a look.";
    }

    public function payload(): array
    {
        return [
            'percent' => $this->percent,
            'direction' => $this->spentLess ? 'down' : 'up',
        ];
    }
}
