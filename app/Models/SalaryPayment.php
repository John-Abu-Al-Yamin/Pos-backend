<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class SalaryPayment extends Model
{
    protected $fillable = [
        'user_id',
        'salary_assignment_id',
        'payment_number',
        'total_amount',
        'payment_date',
        'period_start',
        'period_end',
        'status',
        'notes',
        'confirmed_at',
        'confirmed_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'confirmed_at' => 'datetime',
            'total_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SalaryAssignment::class, 'salary_assignment_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalaryPaymentItem::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function recalculateTotal(): static
    {
        $total = $this->items->sum(function (SalaryPaymentItem $item) {
            $amount = (float) $item->amount;
            return $item->isAddition() ? $amount : -$amount;
        });

        $this->update(['total_amount' => round($total, 2)]);

        return $this->fresh();
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
