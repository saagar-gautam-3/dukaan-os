<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\PurchaseStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class PlatformShopInsights
{
    /**
     * Return platform-safe aggregates only.
     *
     * Last login is intentionally omitted: users has no last-login column and
     * sessions are not shop-scoped, so no per-shop login value can be trusted.
     */
    public function for(Shop $shop): array
    {
        return TenantContext::runAs($shop, function () use ($shop): array {
            $now = now($shop->timezone);
            $nowUtc = $now->copy()->utc();
            $thirtyDaysAgo = $now->copy()->subDays(30)->utc();

            $shop->loadMissing('currentSubscription.plan');

            $usersCount = $shop->shopUsers()->count();
            $productsCount = Product::count();
            $customersCount = Customer::count();
            $salesCount = Sale::count();
            $salesLast30Days = Sale::whereBetween('created_at', [$thirtyDaysAgo, $nowUtc])->count();
            $lastSaleAt = Sale::max('created_at');
            $lastPostedPurchaseAt = Purchase::where('status', PurchaseStatus::Posted->value)->max('posted_at');

            $subscription = $shop->currentSubscription;
            $subscriptionData = null;
            if ($subscription !== null) {
                $subscriptionData = [
                    'plan' => $subscription->plan?->name,
                    'status' => $subscription->status,
                    'starts_at' => $subscription->starts_at,
                    'ends_at' => $subscription->ends_at,
                ];
            }

            return [
                'users_count' => $usersCount,
                'products_count' => $productsCount,
                'customers_count' => $customersCount,
                'sales_count' => $salesCount,
                'sales_last_30_days' => $salesLast30Days,
                'last_sale_at' => $lastSaleAt !== null ? Carbon::parse($lastSaleAt) : null,
                'last_posted_purchase_at' => $lastPostedPurchaseAt !== null ? Carbon::parse($lastPostedPurchaseAt) : null,
                'import_bytes' => $this->importBytes((int) $shop->id),
                'auto_reminders_enabled' => (bool) ($shop->settings['auto_reminders_enabled'] ?? false),
                'subscription' => $subscriptionData,
            ];
        });
    }

    private function importBytes(int $shopId): int
    {
        $dir = storage_path('app/imports/'.$shopId);
        if (! is_dir($dir)) {
            return 0;
        }

        $bytes = 0;
        foreach (File::allFiles($dir) as $file) {
            $bytes += $file->getSize();
        }

        return $bytes;
    }
}
