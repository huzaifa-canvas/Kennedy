<?php

namespace App\Notifications;

use App\Models\SavingsJar;

class SavingsMilestoneNotification extends BaseAppNotification
{
    public function __construct(protected SavingsJar $jar)
    {
    }

    public function type(): string
    {
        return 'savings_milestone';
    }

    public function title(): string
    {
        return "Your {$this->jar->name} reached {$this->jar->progress_percent}% of your goal!";
    }

    public function payload(): array
    {
        return [
            'savings_jar_id' => $this->jar->id,
            'percent' => $this->jar->progress_percent,
        ];
    }
}
