<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Repair extends Model
{
    protected $fillable = [
        'customer_id',
        'customer_name',
        'customer_phone',
        'device_type',
        'device_serial',
        'issue_description',
        'work_description',
        'estimated_cost',
        'parts_cost',
        'deposit',
        'payment_status',
        'completed_at',
        'expected_delivery_date',
        'status',
        'user_id',
        'reference_code',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'parts_cost' => 'decimal:2',
            'deposit' => 'decimal:2',
            'payment_status' => 'string',
            'expected_delivery_date' => 'date:Y-m-d',
        ];
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function markAsPaid(): void
    {
        $this->update(['payment_status' => 'paid']);
    }

    public function markPaymentPartial(): void
    {
        $this->update(['payment_status' => 'partial']);
    }

    protected static function booted(): void
    {
        static::creating(function (Repair $repair) {
            if (empty($repair->reference_code)) {
                $repair->reference_code = static::generateReferenceCode();
            }
        });
    }

    public static function generateReferenceCode(): string
    {
        $prefix = 'RPR-' . now()->format('Ymd') . '-';
        $lastRecord = static::where('reference_code', 'like', "{$prefix}%")
            ->orderBy('reference_code', 'desc')
            ->first();
        $nextNumber = $lastRecord
            ? (int) Str::afterLast($lastRecord->reference_code, '-') + 1
            : 1;
        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function repairParts()
    {
        return $this->hasMany(RepairPart::class);
    }
}
