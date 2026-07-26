<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function addIndexIfNotExists(string $table, string|array $columns, ?string $indexName = null): void
    {
        $indexName = $indexName ?? $table . '_' . (is_array($columns) ? implode('_', $columns) : $columns) . '_index';

        $exists = DB::select("
            SELECT COUNT(*) as cnt FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
        ", [$table, $indexName]);

        if ((int) $exists[0]->cnt === 0) {
            $cols = is_array($columns) ? '`' . implode('`, `', $columns) . '`' : "`$columns`";
            DB::statement("ALTER TABLE `$table` ADD INDEX `$indexName` ($cols)");
        }
    }

    public function up(): void
    {
        $this->addIndexIfNotExists('sales_headers', 'created_at');
        $this->addIndexIfNotExists('purchase_headers', 'completed_at');
        $this->addIndexIfNotExists('purchase_headers', 'status');
        $this->addIndexIfNotExists('sales_return_headers', 'return_date');
        $this->addIndexIfNotExists('purchase_return_headers', 'return_date');
        $this->addIndexIfNotExists('expenses', 'expense_date');
        $this->addIndexIfNotExists('expenses', 'status');
        $this->addIndexIfNotExists('expenses', 'expense_category');
        $this->addIndexIfNotExists('stock_movements', 'created_at');
        $this->addIndexIfNotExists('stock_movements', 'movement_type');
        $this->addIndexIfNotExists('stock_movements', ['reference_type', 'reference_id'], 'stock_movements_reference_type_reference_id_index');
        $this->addIndexIfNotExists('maintenance_headers', 'delivery_date');
        $this->addIndexIfNotExists('inventory_items', 'status');
        $this->addIndexIfNotExists('salary_payments', 'payment_date');
        $this->addIndexIfNotExists('salary_payments', 'status');
        $this->addIndexIfNotExists('products', 'type');
    }

    public function down(): void
    {
        Schema::table('sales_headers', fn($t) => $t->dropIndex(['created_at']));
        Schema::table('purchase_headers', fn($t) => $t->dropIndex(['completed_at']));
        Schema::table('purchase_headers', fn($t) => $t->dropIndex(['status']));
        Schema::table('sales_return_headers', fn($t) => $t->dropIndex(['return_date']));
        Schema::table('purchase_return_headers', fn($t) => $t->dropIndex(['return_date']));
        Schema::table('expenses', fn($t) => $t->dropIndex(['expense_date']));
        Schema::table('expenses', fn($t) => $t->dropIndex(['status']));
        Schema::table('expenses', fn($t) => $t->dropIndex(['expense_category']));
        Schema::table('stock_movements', fn($t) => $t->dropIndex(['created_at']));
        Schema::table('stock_movements', fn($t) => $t->dropIndex(['movement_type']));
        Schema::table('stock_movements', fn($t) => $t->dropIndex(['reference_type', 'reference_id']));
        Schema::table('maintenance_headers', fn($t) => $t->dropIndex(['delivery_date']));
        Schema::table('inventory_items', fn($t) => $t->dropIndex(['status']));
        Schema::table('salary_payments', fn($t) => $t->dropIndex(['payment_date']));
        Schema::table('salary_payments', fn($t) => $t->dropIndex(['status']));
        Schema::table('products', fn($t) => $t->dropIndex(['type']));
    }
};
