<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Exceptions\ImmutableRecordException;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CancelSale
{
    /**
     * Cancel a draft sale. Posted sales cannot be cancelled; use ReturnSale instead.
     */
    public function handle(Sale $sale, int $cancelledById): Sale
    {
        if ($sale->status === SaleStatus::Posted) {
            throw ImmutableRecordException::forSale($sale->id, $sale->status->value);
        }

        if ($sale->status === SaleStatus::Cancelled) {
            throw new InvalidArgumentException(__('errors.sale_already_cancelled', ['id' => $sale->id]));
        }

        return DB::transaction(function () use ($sale) {
            // Lock the row
            $sale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status !== SaleStatus::Draft) {
                throw new InvalidArgumentException(__('errors.sale_cannot_cancel_state', ['id' => $sale->id]));
            }

            $sale->update(['status' => SaleStatus::Cancelled]);

            return $sale->refresh();
        });
    }
}
