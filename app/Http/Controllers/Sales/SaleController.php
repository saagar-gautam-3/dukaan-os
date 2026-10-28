<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\PostSale;
use App\Actions\Sales\ReturnSale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ReturnSaleRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(
        private readonly CreateSale $createSale,
        private readonly PostSale $postSale,
        private readonly CancelSale $cancelSale,
        private readonly ReturnSale $returnSale,
    ) {}

    /** POS screen */
    public function pos(): View
    {
        $this->authorize('create', Sale::class);

        $shop = TenantContext::shop();
        $warehouse = Warehouse::where('shop_id', $shop->id)->where('is_default', true)->first()
            ?? Warehouse::where('shop_id', $shop->id)->first();

        return view('sales.pos', [
            'warehouse' => $warehouse,
        ]);
    }

    /** Product search API for POS cart (JSON) */
    public function searchProducts(Request $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $shop = TenantContext::shop();
        $query = $request->string('q')->toString();

        $products = Product::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%");
            })
            ->with(['stockBalances', 'unit'])
            ->limit(20)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => (string) $p->selling_price,
                'track_stock' => $p->track_stock,
                'unit' => $p->unit?->symbol,
                'stock' => $p->stockBalances->sum('quantity'),
            ]);

        return response()->json($products);
    }

    /** Customer search API for POS (JSON) */
    public function searchCustomers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $shop = TenantContext::shop();
        $query = $request->string('q')->toString();

        $customers = Customer::where('shop_id', $shop->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'phone']);

        return response()->json($customers);
    }

    /** Sales list */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Sale::class);

        $shop = TenantContext::shop();
        $query = Sale::where('shop_id', $shop->id)
            ->with(['customer'])
            ->orderByDesc('created_at');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $sales = $query->paginate(20)->withQueryString();

        return view('sales.index', compact('sales'));
    }

    /** Show a single sale */
    public function show(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->load(['items.product.unit', 'customer', 'paymentAllocations.payment', 'returns.items']);

        return view('sales.show', compact('sale'));
    }

    /** Store draft or directly post from POS */
    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $this->authorize('create', Sale::class);

        $data = $request->validated();
        $sale = $this->createSale->handle($data);

        if ($request->boolean('post_immediately')) {
            $sale = $this->postSale->handle($sale, auth()->id(), [
                'payment_method' => $data['payment_method'] ?? 'cash',
                'payment_amount' => $data['payment_amount'] ?? '0',
                'payment_reference' => $data['payment_reference'] ?? null,
                'idempotency_key' => $request->string('idempotency_key')->toString() ?: null,
            ]);

            return redirect()->route('sales.show', $sale)
                ->with('success', __('sales.sale_posted'));
        }

        return redirect()->route('sales.show', $sale)
            ->with('success', __('sales.draft_saved'));
    }

    /** Post a draft sale */
    public function post(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('post', $sale);

        $request->validate([
            'payment_method' => ['nullable', 'in:cash,bank,qr'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $sale = $this->postSale->handle($sale, auth()->id(), [
            'payment_method' => $request->input('payment_method', 'cash'),
            'payment_amount' => $request->input('payment_amount', '0'),
            'idempotency_key' => $request->string('idempotency_key')->toString() ?: null,
        ]);

        return redirect()->route('sales.show', $sale)
            ->with('success', __('sales.sale_posted'));
    }

    /** Cancel a draft sale */
    public function cancel(Sale $sale): RedirectResponse
    {
        $this->authorize('delete', $sale);

        $this->cancelSale->handle($sale, auth()->id());

        return redirect()->route('sales.index')
            ->with('success', __('sales.sale_cancelled'));
    }

    /** Return form */
    public function returnForm(Sale $sale): View
    {
        $this->authorize('return', $sale);

        $sale->load(['items.product.unit']);

        return view('sales.return', compact('sale'));
    }

    /** Process return */
    public function processReturn(ReturnSaleRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorize('return', $sale);

        $data = $request->validated();
        $filteredItems = array_values(array_filter($data['items'], fn ($i) => (float) ($i['quantity'] ?? 0) > 0));

        if (empty($filteredItems)) {
            return back()->withErrors(['items' => 'Please enter a return quantity greater than 0 for at least one item.']);
        }

        $saleReturn = $this->returnSale->handle($sale, auth()->id(), $filteredItems, $data['reason'] ?? '');

        return redirect()->route('sales.show', $sale)
            ->with('success', __('sales.return_success'));
    }

    /** Printable invoice (A4) */
    public function invoice(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->load(['items.product.unit', 'customer', 'shop']);

        return view('sales.invoice', compact('sale'));
    }

    /** Printable thermal invoice (80mm) */
    public function thermal(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->load(['items.product.unit', 'customer', 'shop']);

        return view('sales.thermal', compact('sale'));
    }
}
