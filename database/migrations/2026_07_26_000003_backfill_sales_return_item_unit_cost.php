<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('sales_return_items')
            || ! Schema::hasTable('sales_items')
            || ! Schema::hasColumn('sales_return_items', 'unit_cost')
            || ! Schema::hasColumn('sales_return_items', 'sales_item_id')
            || ! Schema::hasColumn('sales_items', 'unit_cost')
        ) {
            return;
        }

        $hasUpdatedAt = Schema::hasColumn('sales_return_items', 'updated_at');
        $lastId = 0;

        do {
            $rows = DB::table('sales_return_items as sri')
                ->join('sales_items as si', 'si.id', '=', 'sri.sales_item_id')
                ->where('sri.id', '>', $lastId)
                ->where(function ($query) {
                    $query->whereNull('sri.unit_cost')
                        ->orWhere('sri.unit_cost', 0);
                })
                ->whereNotNull('si.unit_cost')
                ->orderBy('sri.id')
                ->limit(500)
                ->get([
                    'sri.id',
                    'si.unit_cost',
                ]);

            foreach ($rows as $row) {
                $updates = ['unit_cost' => $row->unit_cost];

                if ($hasUpdatedAt) {
                    $updates['updated_at'] = now();
                }

                DB::table('sales_return_items')
                    ->where('id', $row->id)
                    ->update($updates);

                $lastId = (int) $row->id;
            }
        } while ($rows->isNotEmpty());
    }

    public function down(): void
    {
        // Data backfill is intentionally not reversible.
    }
};
