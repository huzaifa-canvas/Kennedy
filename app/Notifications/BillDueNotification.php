<?php

namespace App\Notifications;

use App\Models\BillOccurrence;

class BillDueNotification extends BaseAppNotification
{
    public function __construct(protected BillOccurrence $occurrence)
    {
    }

    public function type(): string
    {
        return 'bill_due';
    }

    public function title(): string
    {
        $amount = number_format((float) $this->occurrence->amount, 2);
        $name = $this->occurrence->bill?->name ?? 'bill';
        $due = $this->occurrence->due_date->format('M j');

        return "Your \${$amount} {$name} bill is due on {$due}.";
    }

    public function payload(): array
    {
        return [
            'bill_id' => $this->occurrence->bill_id,
            'occurrence_id' => $this->occurrence->id,
            'due_date' => $this->occurrence->due_date->toDateString(),
        ];
    }
}
