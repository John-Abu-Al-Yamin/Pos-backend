<?php

namespace App\Models;

use App\Enums\SalaryPaymentItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPaymentItem extends Model
{
    protected $fillable = [
        'salary_payment_id',
        'type',
        'label',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SalaryPayment::class, 'salary_payment_id');
    }

    public function isAddition(): bool
    {
        return SalaryPaymentItemType::tryFrom($this->type)?->isAddition() ?? true;
    }

    public function isDeduction(): bool
    {
        return !$this->isAddition();
    }

    public function effectiveAmount(): float
    {
        $amount = round((float) $this->amount, 2);
        return $this->isAddition() ? $amount : -$amount;
    }
}
