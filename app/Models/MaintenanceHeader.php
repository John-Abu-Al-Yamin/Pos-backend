<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceHeader extends Model
{
    protected $fillable = [
        'maintenance_device_id',
        'customer_id',
        'ticket_number',
        'status',
        'problem_description',
        'received_date',
        'delivery_date',
        'total_cost',
        'advance_payment',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'total_cost' => 'decimal:2',
        'advance_payment' => 'decimal:2',
        'received_date' => 'date',
        'delivery_date' => 'date',
    ];

    protected $appends = ['labor_cost', 'parts_total', 'grand_total', 'remaining_amount'];

    public function maintenanceDevice()
    {
        return $this->belongsTo(MaintenanceDevice::class, 'maintenance_device_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function operations()
    {
        return $this->hasMany(MaintenanceOperation::class, 'maintenance_header_id');
    }

    public function usedParts()
    {
        return $this->hasMany(MaintenanceUsedPart::class, 'maintenance_header_id');
    }

    public function recalculateTotalCost(): void
    {
        $partsTotal = $this->usedParts()->sum('total_price') ?? 0;
        $operationsTotal = $this->operations()->sum('cost') ?? 0;
        
        // We use query builder to avoid firing events and causing recursive loops,
        // although saving the model with update() might be fine, it's safer this way 
        // to avoid triggering observers if any are added later to MaintenanceHeader.
        // Or we can just use updateQuietly() which is standard in Laravel 8+.
        $this->updateQuietly([
            'total_cost' => $partsTotal + $operationsTotal
        ]);
    }

    public function getLaborCostAttribute(): float
    {
        return (float) ($this->attributes['operations_sum_cost'] ?? 0);
    }

    public function getPartsTotalAttribute(): float
    {
        return (float) ($this->attributes['used_parts_sum_total_price'] ?? 0);
    }

    public function getGrandTotalAttribute(): float
    {
        return (float) $this->total_cost;
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->total_cost - (float) $this->advance_payment);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['pending', 'under_repair', 'waiting_parts']);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['delivered', 'cancelled']);
    }
}
