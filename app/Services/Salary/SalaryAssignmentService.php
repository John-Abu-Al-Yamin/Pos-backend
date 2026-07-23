<?php

namespace App\Services\Salary;

use App\Models\SalaryAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SalaryAssignmentService
{
    public function assignSalary(User $user, array $data): SalaryAssignment
    {
        return DB::transaction(function () use ($user, $data) {
            return SalaryAssignment::create([
                'user_id' => $user->id,
                'base_salary' => $data['base_salary'],
                'payment_frequency' => $data['payment_frequency'] ?? 'monthly',
                'reason' => $data['reason'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function updateAssignment(SalaryAssignment $assignment, array $data): SalaryAssignment
    {
        return DB::transaction(function () use ($assignment, $data) {
            $updateData = $data;
            $updateData['updated_by'] = auth()->id();

            $assignment->update($updateData);

            return $assignment->fresh();
        });
    }
}
