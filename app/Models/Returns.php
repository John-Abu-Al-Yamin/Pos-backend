<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Returns extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'sale_id',
        'customer_id',
        'user_id',
        'return_date',
        'refund_method',
        'refund_total',
        'restocking_fee',
        'reason',
        'notes',
        'reference_code',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date:Y-m-d',
            'refund_total' => 'decimal:2',
            'restocking_fee' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Returns $return) {
            if (empty($return->reference_code)) {
                $return->reference_code = static::generateReferenceCode();
            }
        });
    }

    public static function generateReferenceCode(): string
    {
        $prefix = 'RET-' . now()->format('Ymd') . '-';

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

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function returnItems()
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}
