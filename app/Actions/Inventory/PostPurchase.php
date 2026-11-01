<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Udhar\AddSupplierLedgerEntry;
use App\Enums\LedgerEntryType;
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
        protected ReceiveStock $receiveStock,
        protected AddSupplierLedgerEntry $addSupplierLedgerEntry,
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
            throw new InvalidArgumentException(__('errors.cancelled_purchase_cannot_post'));
        }

        return DB::transaction(function () use ($purchase, $userId) {
            /** @var Warehouse $warehouse */
            $warehouse = $purchase->warehouse;

            $purchase->loadMissing('items.product');

            if ($purchase->items->isEmpty()) {
                throw new InvalidArgumentException(__('errors.purchase_line_items_required'));
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

            // Write supplier ledger debit (we now owe the supplier)
            if ($purchase->supplier_id !== null) {
                $purchase->loadMissing('supplier');
                if ($purchase->supplier) {
                    $this->addSupplierLedgerEntry->handle($purchase->supplier, [
                        'entry_type' => LedgerEntryType::PurchaseDebit,
                        'debit' => (string) $purchase->grand_total,
                        'reference_type' => 'purchase',
                        'reference_id' => $purchase->id,
                        'notes' => "Purchase #{$purchase->purchase_no} posted",
                        'created_by' => $userId,
                    ]);
                }
            }

            return $purchase;
        });
    }
}
