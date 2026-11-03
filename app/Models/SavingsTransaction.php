<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsTransaction extends Model
{
    protected $fillable = [
        'savings_jar_id', 'user_id', 'type', 'amount', 'note', 'occurred_on',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
        ];
    }

    public function jar(): BelongsTo
    {
        return $this->belongsTo(SavingsJar::class, 'savings_jar_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
