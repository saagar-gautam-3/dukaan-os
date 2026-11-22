<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\HeldSale;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteHeldSale
{
    public function handle(HeldSale $heldSale): void
    {
        $shop = TenantContext::shop();

        DB::transaction(function () use ($heldSale): void {
            $locked = HeldSale::where('id', $heldSale->id)->lockForUpdate()->first();

            if (! $locked) {
                throw new InvalidArgumentException(__('errors.held_sale_not_found'));
            }

            $locked->delete();
        });
    }
}
