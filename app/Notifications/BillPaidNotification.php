<?php

namespace App\Notifications;

use App\Models\BillOccurrence;

class BillPaidNotification extends BaseAppNotification
{
    public function __construct(protected BillOccurrence $occurrence)
    {
    }

    public function type(): string
    {
        return 'bill_paid';
    }

    public function title(): string
    {
        $amount = number_format((float) ($this->occurrence->paid_amount ?? $this->occurrence->amount), 2);
        $name = $this->occurrence->bill?->name ?? 'bill';

        return "Your \${$amount} {$name} payment has been recorded.";
    }

    public function payload(): array
    {
        return [
            'bill_id' => $this->occurrence->bill_id,
            'occurrence_id' => $this->occurrence->id,
        ];
    }
}
