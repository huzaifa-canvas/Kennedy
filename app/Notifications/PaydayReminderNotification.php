<?php

namespace App\Notifications;

/**
 * "You received $1,600. Set aside $560 for upcoming bills."
 *
 * Raised by the cron on the morning of a payday, so the reminder arrives even
 * if the user has not opened the app to record the paycheck yet.
 */
class PaydayReminderNotification extends BaseAppNotification
{
    public function __construct(
        protected float $expectedAmount,
        protected float $setAside,
    ) {
    }

    public function type(): string
    {
        return 'paycheck_received';
    }

    public function title(): string
    {
        $amount = number_format($this->expectedAmount, 2);
        $aside = number_format($this->setAside, 2);

        return "Payday! You should receive \${$amount}. Set aside \${$aside} for upcoming bills.";
    }

    public function payload(): array
    {
        return [
            'expected_amount' => round($this->expectedAmount, 2),
            'set_aside' => round($this->setAside, 2),
        ];
    }
}
