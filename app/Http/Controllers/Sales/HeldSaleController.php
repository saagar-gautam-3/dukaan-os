<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\DeleteHeldSale;
use App\Actions\Sales\HoldSale;
use App\Actions\Sales\ResumeHeldSale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\HoldSaleRequest;
use App\Models\HeldSale;
use App\Models\Product;
use App\Models\Sale;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Throwable;

class HeldSaleController extends Controller
{
    public function __construct(
        private readonly HoldSale $holdSale,
        private readonly ResumeHeldSale $resumeHeldSale,
        private readonly DeleteHeldSale $deleteHeldSale,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $heldSales = HeldSale::with(['user', 'customer'])->orderBy('created_at', 'desc')->get();

        $shopId = (int) TenantContext::getShopId();
        $data = $heldSales->map(function (HeldSale $heldSale) use ($shopId) {
            $cart = is_array($heldSale->cart) ? $heldSale->cart : [];
            $productIds = array_values(array_unique(array_map(
                static fn ($line) => (int) ($line['product_id'] ?? 0),
                $cart
            )));
            $products = Product::where('shop_id', $shopId)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $total = '0.00';
            foreach ($cart as $line) {
                $product = $products->get((int) ($line['product_id'] ?? 0));
                if ($product) {
                    $total = Money::add(
                        $total,
                        Money::multiply((string) $product->selling_price, (string) ($line['quantity'] ?? '0'), 2),
                        2
                    );
                }
            }

            return [
                'id' => $heldSale->id,
                'label' => $heldSale->label,
                'customer_id' => $heldSale->customer_id,
                'customer_name' => $heldSale->customer?->name,
                'line_count' => count($cart),
                'held_at' => $heldSale->created_at?->toIso8601String(),
                'held_by' => $heldSale->user?->name,
                'approximate_total' => $total,
            ];
        })->values();

        return response()->json($data);
    }

    public function store(HoldSaleRequest $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        try {
            $heldSale = $this->holdSale->handle($request->validated());

            return response()->json([
                'id' => $heldSale->id,
                'message' => __('sales.held_bill_saved'),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('common.error_generic')], 500);
        }
    }

    public function resume(HeldSale $heldSale): JsonResponse
    {
        $this->authorize('create', Sale::class);

        try {
            $result = $this->resumeHeldSale->handle($heldSale);

            return response()->json($result);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('common.error_generic')], 500);
        }
    }

    public function destroy(HeldSale $heldSale): JsonResponse
    {
        $this->authorize('create', Sale::class);

        try {
            $this->deleteHeldSale->handle($heldSale);

            return response()->json(['message' => __('sales.held_bill_deleted')]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('common.error_generic')], 500);
        }
    }
}
