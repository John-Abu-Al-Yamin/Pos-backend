<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Models\MaintenanceHeader;
use App\Models\PurchaseHeader;
use App\Models\PurchaseReturnHeader;
use App\Models\SalaryPayment;
use App\Models\SalesHeader;
use App\Models\SalesReturnHeader;
use App\Models\StockMovement;
use App\Models\UsedDevicePurchaseHeader;
use App\Models\User;
use App\Observers\AuditableObserver;
use App\Services\Dashboard\DashboardCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('view-dashboard', fn (User $user) => in_array($user->role, ['admin', 'employee'], true)
        );

        Gate::define('view-dashboard-financials', fn (User $user) => $user->role === 'admin'
        );

        $this->clearDashboardCacheWhenSaved([
            SalesHeader::class,
            SalesReturnHeader::class,
            PurchaseHeader::class,
            PurchaseReturnHeader::class,
            UsedDevicePurchaseHeader::class,
            InventoryQuantity::class,
            InventoryItem::class,
            StockMovement::class,
            Expense::class,
            MaintenanceHeader::class,
            SalaryPayment::class,
        ]);

        $this->registerAuditObservers();
    }

    private function clearDashboardCacheWhenSaved(array $models): void
    {
        foreach ($models as $model) {
            /** @var class-string<Model> $model */
            $model::saved(fn () => app(DashboardCacheService::class)->clear());
            $model::deleted(fn () => app(DashboardCacheService::class)->clear());
        }
    }

    private function registerAuditObservers(): void
    {
        foreach (array_keys(config('audit.models', [])) as $model) {
            if (is_a($model, Model::class, true)) {
                /** @var class-string<Model> $model */
                $model::observe(AuditableObserver::class);
            }
        }
    }
}
