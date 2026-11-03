<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paycheck extends Model
{
    protected $fillable = [
        'user_id', 'income_source_id', 'amount', 'set_aside_amount', 'received_on',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'set_aside_amount' => 'decimal:2',
            'received_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function incomeSource(): BelongsTo
    {
        return $this->belongsTo(IncomeSource::class);
    }
}
