<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Purchase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QueryPurchases
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Purchase::with(['supplier', 'warehouse', 'createdBy'])->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', (int) $filters['supplier_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('purchase_no', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
