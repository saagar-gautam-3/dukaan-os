<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Sale;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QuerySales
{
    public function handle(?string $search = null, ?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        $shop = TenantContext::shop();
        $query = Sale::where('shop_id', $shop->id)
            ->with(['customer'])
            ->orderByDesc('created_at');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
