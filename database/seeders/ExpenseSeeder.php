<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@pos.com')->first();

        $expenses = [
            // April 2026
            ['title' => 'إيجار المحل - أبريل', 'category' => 'Rent', 'amount' => 1500.00, 'expense_date' => '2026-04-01', 'created_by' => $admin->id],
            ['title' => 'رواتب الموظفين - أبريل', 'category' => 'Salaries', 'amount' => 5000.00, 'expense_date' => '2026-04-28', 'created_by' => $admin->id],
            ['title' => 'فاتورة كهرباء - أبريل', 'category' => 'Electricity', 'amount' => 320.50, 'expense_date' => '2026-04-10', 'created_by' => $admin->id],
            ['title' => 'فاتورة مياه - أبريل', 'category' => 'Water', 'amount' => 45.00, 'expense_date' => '2026-04-10', 'created_by' => $admin->id],
            ['title' => 'اشتراك الإنترنت - أبريل', 'category' => 'Internet', 'amount' => 100.00, 'expense_date' => '2026-04-05', 'created_by' => $admin->id],
            ['title' => 'خدمات تنظيف - أبريل', 'category' => 'Cleaning', 'amount' => 100.00, 'expense_date' => '2026-04-15', 'created_by' => $admin->id],
            ['title' => 'حملة إعلانية فيسبوك - أبريل', 'category' => 'Marketing', 'amount' => 500.00, 'expense_date' => '2026-04-12', 'created_by' => $admin->id],
            ['title' => 'قرطاسية ومستلزمات مكتب', 'category' => 'Office Supplies', 'amount' => 85.75, 'expense_date' => '2026-04-08', 'created_by' => $admin->id],
            ['title' => 'صيانة مكيف الهواء', 'category' => 'Maintenance', 'amount' => 200.00, 'expense_date' => '2026-04-20', 'created_by' => $admin->id],
            ['title' => 'مواصلات - توصيل مشتريات', 'category' => 'Transportation', 'amount' => 65.00, 'expense_date' => '2026-04-18', 'created_by' => $admin->id],

            // May 2026
            ['title' => 'إيجار المحل - مايو', 'category' => 'Rent', 'amount' => 1500.00, 'expense_date' => '2026-05-01', 'created_by' => $admin->id],
            ['title' => 'رواتب الموظفين - مايو', 'category' => 'Salaries', 'amount' => 5200.00, 'expense_date' => '2026-05-28', 'created_by' => $admin->id],
            ['title' => 'فاتورة كهرباء - مايو', 'category' => 'Electricity', 'amount' => 295.00, 'expense_date' => '2026-05-10', 'created_by' => $admin->id],
            ['title' => 'فاتورة مياه - مايو', 'category' => 'Water', 'amount' => 38.00, 'expense_date' => '2026-05-10', 'created_by' => $admin->id],
            ['title' => 'اشتراك الإنترنت - مايو', 'category' => 'Internet', 'amount' => 100.00, 'expense_date' => '2026-05-05', 'created_by' => $admin->id],
            ['title' => 'خدمات تنظيف - مايو', 'category' => 'Cleaning', 'amount' => 100.00, 'expense_date' => '2026-05-15', 'created_by' => $admin->id],
            ['title' => 'أوراق طباعة وإعلانات', 'category' => 'Office Supplies', 'amount' => 120.00, 'expense_date' => '2026-05-12', 'created_by' => $admin->id],
            ['title' => 'صيانة نظام الأمن والمراقبة', 'category' => 'Maintenance', 'amount' => 350.00, 'expense_date' => '2026-05-22', 'created_by' => $admin->id],
            ['title' => 'مواصلات - شحن بضائع', 'category' => 'Transportation', 'amount' => 90.00, 'expense_date' => '2026-05-18', 'created_by' => $admin->id],

            // June 2026
            ['title' => 'إيجار المحل - يونيو', 'category' => 'Rent', 'amount' => 1500.00, 'expense_date' => '2026-06-01', 'created_by' => $admin->id],
            ['title' => 'رواتب الموظفين - يونيو', 'category' => 'Salaries', 'amount' => 5200.00, 'expense_date' => '2026-06-28', 'created_by' => $admin->id],
            ['title' => 'فاتورة كهرباء - يونيو', 'category' => 'Electricity', 'amount' => 340.00, 'expense_date' => '2026-06-10', 'created_by' => $admin->id],
            ['title' => 'فاتورة مياه - يونيو', 'category' => 'Water', 'amount' => 42.00, 'expense_date' => '2026-06-10', 'created_by' => $admin->id],
            ['title' => 'اشتراك الإنترنت - يونيو', 'category' => 'Internet', 'amount' => 100.00, 'expense_date' => '2026-06-05', 'created_by' => $admin->id],
            ['title' => 'خدمات تنظيف - يونيو', 'category' => 'Cleaning', 'amount' => 100.00, 'expense_date' => '2026-06-15', 'created_by' => $admin->id],
            ['title' => 'مصروفات ضريبية - الربع الثاني', 'category' => 'Taxes', 'amount' => 1200.00, 'expense_date' => '2026-06-25', 'created_by' => $admin->id],
            ['title' => 'مستلزمات تغليف وشحن', 'category' => 'Miscellaneous', 'amount' => 65.00, 'expense_date' => '2026-06-08', 'created_by' => $admin->id],
        ];

        foreach ($expenses as $expense) {
            Expense::create($expense);
        }
    }
}
