<?php

namespace App\Enums;

enum SalaryPaymentItemType: string
{
    case BASE_SALARY = 'base_salary';
    case OVERTIME = 'overtime';
    case BONUS = 'bonus';
    case COMMISSION = 'commission';
    case DEDUCTION = 'deduction';
    case ADVANCE_REPAYMENT = 'advance_repayment';
    case ADJUSTMENT = 'adjustment';

    public function isAddition(): bool
    {
        return match ($this) {
            self::DEDUCTION, self::ADVANCE_REPAYMENT => false,
            default => true,
        };
    }

    public function isDeduction(): bool
    {
        return !$this->isAddition();
    }

    public static function values(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }
}
