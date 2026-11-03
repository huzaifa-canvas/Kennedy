<?php

namespace App\Notifications;

use App\Models\Paycheck;

class PaycheckReceivedNotification extends BaseAppNotification
{
    public function __construct(
        protected Paycheck $paycheck,
        protected float $setAside,
    ) {
    }

    public function type(): string
    {
        return 'paycheck_received';
    }

    public function title(): string
    {
        $amount = number_format((float) $this->paycheck->amount, 2);
        $aside = number_format($this->setAside, 2);

        return "You received \${$amount}. Set aside \${$aside} for upcoming bills.";
    }

    public function payload(): array
    {
        return [
            'paycheck_id' => $this->paycheck->id,
            'set_aside' => round($this->setAside, 2),
        ];
    }
}
