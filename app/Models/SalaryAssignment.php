<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class SalaryAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'base_salary',
        'payment_frequency',
        'reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class, 'salary_assignment_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
