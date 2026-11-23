<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Supplier;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QuerySuppliers
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Supplier::query();

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('pan_vat', 'like', "%{$search}%");
            });
        }

        return $query
            ->withSum('ledgerEntries as debit_sum', 'debit')
            ->withSum('ledgerEntries as credit_sum', 'credit')
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Supplier $supplier): Supplier {
                $supplier->balance = Money::subtract(
                    (string) ($supplier->debit_sum ?? '0'),
                    (string) ($supplier->credit_sum ?? '0'),
                    2,
                );

                return $supplier;
            });
    }
}
