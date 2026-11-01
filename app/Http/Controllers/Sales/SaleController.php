<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\FindIdempotentSale;
use App\Actions\Sales\PostSale;
use App\Actions\Sales\QuerySaleDetails;
use App\Actions\Sales\QuerySales;
use App\Actions\Sales\ResolvePosWarehouse;
use App\Actions\Sales\ReturnSale;
use App\Actions\Sales\SearchPosCustomers;
use App\Actions\Sales\SearchPosProducts;
use App\Exceptions\ImmutableRecordException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidReturnException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ReturnSaleRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Models\Customer;
use App\Models\Sale;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class SaleController extends Controller
{
    public function __construct(
        private readonly CreateSale $createSale,
        private readonly PostSale $postSale,
        private readonly CancelSale $cancelSale,
        private readonly ReturnSale $returnSale,
        private readonly QuerySales $querySales,
        private readonly SearchPosProducts $searchPosProducts,
        private readonly SearchPosCustomers $searchPosCustomers,
        private readonly ResolvePosWarehouse $resolvePosWarehouse,
        private readonly FindIdempotentSale $findIdempotentSale,
        private readonly QuerySaleDetails $querySaleDetails,
    ) {}

    /**
     * POS screen.
     */
    public function pos(): View
    {
        $this->authorize('create', Sale::class);

        $warehouse = $this->resolvePosWarehouse->handle();

        return view('sales.pos', [
            'warehouse' => $warehouse,
        ]);
    }

    /**
     * Product search JSON API for POS cart.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $products = $this->searchPosProducts->handle($request->string('q')->toString());

        return response()->json($products);
    }

    /**
     * Customer search JSON API for POS.
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $customers = $this->searchPosCustomers->handle($request->string('q')->toString());

        return response()->json($customers);
    }

    /**
     * Filtered list of sales.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Sale::class);

        $sales = $this->querySales->handle(
            $request->string('search')->toString() ?: null,
            $request->string('status')->toString() ?: null
        );

        return view('sales.index', compact('sales'));
    }

    /**
     * Show a single sale.
     */
    public function show(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $this->querySaleDetails->show($sale);

        return view('sales.show', compact('sale'));
    }

    /**
     * Store draft or post directly from POS.
     */
    public function store(StoreSaleRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Sale::class);

        $idemKey = $request->string('idempotency_key')->toString() ?: null;
        if ($idemKey !== null) {
            $existingSale = $this->findIdempotentSale->handle($idemKey);

            if ($existingSale) {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'redirect' => route('sales.show', $existingSale),
                        'sale_id' => $existingSale->id,
                        'message' => __('sales.sale_posted'),
                    ]);
                }

                return redirect()->route('sales.show', $existingSale)
                    ->with('status', __('sales.sale_posted'));
            }
        }

        try {
            $data = $request->validated();
            $sale = $this->createSale->handle($data);

            if ($request->boolean('post_immediately')) {
                $sale = $this->postSale->handle($sale, (int) auth()->id(), [
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'payment_amount' => $data['payment_amount'] ?? '0',
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'idempotency_key' => $idemKey,
                ]);

                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'redirect' => route('sales.show', $sale),
                        'sale_id' => $sale->id,
                        'message' => __('sales.sale_posted'),
                    ]);
                }

                return redirect()->route('sales.show', $sale)
                    ->with('status', __('sales.sale_posted'));
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'redirect' => route('sales.show', $sale),
                    'sale_id' => $sale->id,
                    'message' => __('sales.draft_saved'),
                ]);
            }

            return redirect()->route('sales.show', $sale)
                ->with('status', __('sales.draft_saved'));
        } catch (InsufficientStockException|ImmutableRecordException|InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => __('common.error_generic')], 500);
            }

            return back()->withInput()->with('error', __('common.error_generic'));
        }
    }

    /**
     * Post a draft sale.
     */
    public function post(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('post', $sale);

        $request->validate([
            'payment_method' => ['nullable', 'in:cash,bank,qr'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $sale = $this->postSale->handle($sale, (int) auth()->id(), [
                'payment_method' => $request->input('payment_method', 'cash'),
                'payment_amount' => $request->input('payment_amount', '0'),
                'idempotency_key' => $request->string('idempotency_key')->toString() ?: null,
            ]);

            return redirect()->route('sales.show', $sale)
                ->with('status', __('sales.sale_posted'));
        } catch (InsufficientStockException|ImmutableRecordException|InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }
    }

    /**
     * Cancel a draft sale.
     */
    public function cancel(Sale $sale): RedirectResponse
    {
        $this->authorize('delete', $sale);

        try {
            $this->cancelSale->handle($sale, (int) auth()->id());

            return redirect()->route('sales.index')
                ->with('status', __('sales.sale_cancelled'));
        } catch (ImmutableRecordException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.error_generic'));
        }
    }

    /**
     * Return form.
     */
    public function returnForm(Sale $sale): View
    {
        $this->authorize('return', $sale);

        $this->querySaleDetails->returnForm($sale);

        return view('sales.return', compact('sale'));
    }

    /**
     * Process return.
     */
    public function processReturn(ReturnSaleRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorize('return', $sale);

        try {
            $data = $request->validated();
            $filteredItems = array_values(array_filter($data['items'], fn ($i) => Money::compare((string) ($i['quantity'] ?? '0'), '0', 3) > 0));

            if (empty($filteredItems)) {
                return back()->withInput()->with('error', __('errors.return_quantity_required'));
            }

            $this->returnSale->handle($sale, (int) auth()->id(), $filteredItems, $data['reason'] ?? '');

            return redirect()->route('sales.show', $sale)
                ->with('status', __('sales.return_success'));
        } catch (InvalidReturnException|ImmutableRecordException|InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }
    }

    /**
     * Printable invoice (A4).
     */
    public function invoice(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $this->querySaleDetails->invoice($sale);

        return view('sales.invoice', compact('sale'));
    }

    /**
     * Printable thermal invoice (80mm).
     */
    public function thermal(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $this->querySaleDetails->invoice($sale);

        return view('sales.thermal', compact('sale'));
    }
}
