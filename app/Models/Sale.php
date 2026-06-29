<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Sale extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'created_by_name',
        'date',
        'total',
        'payment_method',
        'payment_received_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'reference_code',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'total' => 'decimal:2',
            'payment_received_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function scopeNotVoided($query)
    {
        return $query->whereNull('voided_at');
    }

    protected static function booted(): void
    {
        static::creating(function (Sale $sale) {
            if (empty($sale->reference_code)) {
                $sale->reference_code = static::generateReferenceCode();
            }
        });
    }

    public static function generateReferenceCode(): string
    {
        $prefix = 'SALE-' . now()->format('Ymd') . '-';

        $lastRecord = static::where('reference_code', 'like', "{$prefix}%")
            ->orderBy('reference_code', 'desc')
            ->first();

        if ($lastRecord) {
            $lastNumber = (int) Str::afterLast($lastRecord->reference_code, '-');
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function returns()
    {
        return $this->hasMany(Returns::class);
    }

    public function recalculateTotal(): void
    {
        $this->total = $this->saleItems()->sum('line_total');
        $this->saveQuietly();
    }
}
