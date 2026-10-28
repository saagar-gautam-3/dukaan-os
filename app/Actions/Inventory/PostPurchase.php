<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Enums\PurchaseStatus;
use App\Exceptions\ImmutableRecordException;
use App\Models\Purchase;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostPurchase
{
    public function __construct(
        protected ReceiveStock $receiveStock
    ) {}

    /**
     * Post a draft purchase: receives stock for each line item, updates moving weighted
     * average product costs, and locks the purchase into immutable posted state.
     */
    public function execute(Purchase $purchase, ?int $userId = null): Purchase
    {
        if ($purchase->isPosted()) {
            throw ImmutableRecordException::forPurchase($purchase->id, $purchase->status->value);
        }

        if ($purchase->status === PurchaseStatus::Cancelled) {
            throw new InvalidArgumentException('Cancelled purchases cannot be posted.');
        }

        return DB::transaction(function () use ($purchase, $userId) {
            /** @var Warehouse $warehouse */
            $warehouse = $purchase->warehouse;

            $purchase->loadMissing('items.product');

            if ($purchase->items->isEmpty()) {
                throw new InvalidArgumentException('Cannot post a purchase with no line items.');
            }

            foreach ($purchase->items as $item) {
                $this->receiveStock->execute(
                    product: $item->product,
                    warehouse: $warehouse,
                    quantity: $item->quantity,
                    unitCost: $item->unit_cost,
                    movementType: MovementType::PurchaseReceive,
                    referenceType: Purchase::class,
                    referenceId: $purchase->id,
                    notes: "Purchase #{$purchase->purchase_no}",
                    userId: $userId ?? auth()->id()
                );
            }

            $purchase->status = PurchaseStatus::Posted;
            $purchase->posted_at = now();
            $purchase->save();

            return $purchase;
        });
    }
}
