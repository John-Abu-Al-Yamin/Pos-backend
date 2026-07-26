<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function addIndexIfNotExists(string $table, array $columns, string $indexName): void
    {
        $exists = DB::select("
            SELECT COUNT(*) as cnt FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
        ", [$table, $indexName]);

        if ((int) $exists[0]->cnt === 0) {
            $cols = '`' . implode('`, `', $columns) . '`';
            DB::statement("ALTER TABLE `$table` ADD INDEX `$indexName` ($cols)");
        }
    }

    public function up(): void
    {
        $this->addIndexIfNotExists('sales_headers', ['created_at', 'customer_id', 'created_by'], 'sales_headers_report_filters_index');
        $this->addIndexIfNotExists('sales_items', ['sales_header_id', 'product_id'], 'sales_items_header_product_index');
        $this->addIndexIfNotExists('sales_return_headers', ['return_date', 'customer_id', 'user_id'], 'sales_return_headers_report_filters_index');
        $this->addIndexIfNotExists('sales_return_items', ['sales_return_header_id', 'product_id'], 'sales_return_items_header_product_index');

        $this->addIndexIfNotExists('purchase_headers', ['status', 'completed_at', 'supplier_id', 'created_by'], 'purchase_headers_report_filters_index');
        $this->addIndexIfNotExists('purchase_items', ['purchase_header_id', 'product_id'], 'purchase_items_header_product_index');
        $this->addIndexIfNotExists('purchase_return_headers', ['return_date', 'supplier_id', 'user_id'], 'purchase_return_headers_report_filters_index');
        $this->addIndexIfNotExists('purchase_return_items', ['purchase_return_header_id', 'product_id'], 'purchase_return_items_header_product_index');

        $this->addIndexIfNotExists('maintenance_headers', ['status', 'received_date', 'customer_id'], 'maintenance_headers_received_report_index');
        $this->addIndexIfNotExists('maintenance_headers', ['status', 'delivery_date', 'customer_id'], 'maintenance_headers_delivery_report_index');
        $this->addIndexIfNotExists('maintenance_headers', ['status', 'updated_at', 'customer_id'], 'maintenance_headers_updated_report_index');
        $this->addIndexIfNotExists('maintenance_used_parts', ['maintenance_header_id', 'product_id'], 'maintenance_used_parts_header_product_index');

        $this->addIndexIfNotExists('expenses', ['status', 'expense_date', 'expense_category'], 'expenses_accrual_report_index');
        $this->addIndexIfNotExists('expenses', ['status', 'payment_date', 'expense_category'], 'expenses_cash_report_index');

        $this->addIndexIfNotExists('salary_payments', ['status', 'payment_date', 'user_id'], 'salary_payments_report_filters_index');
        $this->addIndexIfNotExists('inventory_items', ['status', 'product_id'], 'inventory_items_status_product_index');
        $this->addIndexIfNotExists('products', ['type', 'category_id', 'brand_id'], 'products_report_filters_index');
        $this->addIndexIfNotExists('stock_movements', ['movement_type', 'created_at'], 'stock_movements_type_date_index');
    }

    public function down(): void
    {
        Schema::table('sales_headers', fn ($table) => $table->dropIndex('sales_headers_report_filters_index'));
        Schema::table('sales_items', fn ($table) => $table->dropIndex('sales_items_header_product_index'));
        Schema::table('sales_return_headers', fn ($table) => $table->dropIndex('sales_return_headers_report_filters_index'));
        Schema::table('sales_return_items', fn ($table) => $table->dropIndex('sales_return_items_header_product_index'));

        Schema::table('purchase_headers', fn ($table) => $table->dropIndex('purchase_headers_report_filters_index'));
        Schema::table('purchase_items', fn ($table) => $table->dropIndex('purchase_items_header_product_index'));
        Schema::table('purchase_return_headers', fn ($table) => $table->dropIndex('purchase_return_headers_report_filters_index'));
        Schema::table('purchase_return_items', fn ($table) => $table->dropIndex('purchase_return_items_header_product_index'));

        Schema::table('maintenance_headers', fn ($table) => $table->dropIndex('maintenance_headers_received_report_index'));
        Schema::table('maintenance_headers', fn ($table) => $table->dropIndex('maintenance_headers_delivery_report_index'));
        Schema::table('maintenance_headers', fn ($table) => $table->dropIndex('maintenance_headers_updated_report_index'));
        Schema::table('maintenance_used_parts', fn ($table) => $table->dropIndex('maintenance_used_parts_header_product_index'));

        Schema::table('expenses', fn ($table) => $table->dropIndex('expenses_accrual_report_index'));
        Schema::table('expenses', fn ($table) => $table->dropIndex('expenses_cash_report_index'));

        Schema::table('salary_payments', fn ($table) => $table->dropIndex('salary_payments_report_filters_index'));
        Schema::table('inventory_items', fn ($table) => $table->dropIndex('inventory_items_status_product_index'));
        Schema::table('products', fn ($table) => $table->dropIndex('products_report_filters_index'));
        Schema::table('stock_movements', fn ($table) => $table->dropIndex('stock_movements_type_date_index'));
    }
};
