<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialLedger extends Model
{
    protected $table = 'financial_ledger';

    protected $fillable = [
        'event_type',
        'amount',
        'direction',
        'occurred_at',
        'reference_type',
        'reference_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
