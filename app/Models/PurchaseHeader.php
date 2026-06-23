<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PurchaseHeader extends Model
{
    protected $fillable = [
        'supplier_id',
        'date',
        'total',
        'type',
        'reference',
        'reference_code',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaseHeader $purchaseHeader) {
            if (empty($purchaseHeader->reference_code)) {
                $purchaseHeader->reference_code = static::generateReferenceCode($purchaseHeader->type);
            }
        });
    }

    public static function generateReferenceCode(string $type): string
    {
        $year = now()->year;
        $typeCode = Str::upper(Str::replace(' ', '_', $type));
        $prefix = "BY-{$typeCode}-{$year}-";

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

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function recalculateTotal(): void
    {
        $this->total = $this->purchaseItems()->sum('line_total');
        $this->saveQuietly();
    }
}
