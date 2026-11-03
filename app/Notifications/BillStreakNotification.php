<?php

namespace App\Notifications;

/**
 * "You've paid all bills on time for 2 weeks!"
 */
class BillStreakNotification extends BaseAppNotification
{
    public function __construct(protected int $weeks)
    {
    }

    public function type(): string
    {
        return 'streak';
    }

    public function title(): string
    {
        $unit = $this->weeks === 1 ? 'week' : 'weeks';

        return "You have paid all bills on time for {$this->weeks} {$unit}!";
    }

    public function payload(): array
    {
        return ['weeks' => $this->weeks];
    }
}
