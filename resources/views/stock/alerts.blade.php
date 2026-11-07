@extends('layouts.app')

@section('title', __('inventory.low_stock_alerts') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>{{ __('inventory.low_stock_alerts') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('inventory.low_stock_desc') }}</p>
        </div>

        <div class="action-row mt-3 mt-md-0 d-flex gap-2">
            <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>{{ __('common.back') }}
            </a>
            @canRoute('purchases.create')
            <a href="{{ route('purchases.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-cart-plus me-1"></i>{{ __('purchases.create_purchase') }}
            </a>
            @endCanRoute
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                {{ __('inventory.alert_count') }} ({{ $products->count() }})
            </h5>
        </div>
        <div class="card-body p-0">
            @if($products->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-check-circle text-success fs-1 d-block mb-2"></i>
                    <h5 class="fw-bold text-dark">{{ __('inventory.all_healthy') }}</h5>
                    <p class="mb-0 text-muted">{{ __('inventory.no_alerts') }}</p>
                </div>
            @else
                @php
                    $groups = $products->groupBy(fn ($product) => $product->preferred_supplier?->id ?? 0);
                @endphp
                @foreach($groups as $supplierId => $items)
                    @php
                        $supplier = $items->first()->preferred_supplier;
                        $canOrder = ($supplier !== null) && \App\Support\NepaliPhone::isValidMobile($supplier->phone);
                        $orderItems = collect();
                        foreach ($items as $item) {
                            $orderItems->push($item->name.' x '.\App\Support\NepaliNumber::formatQuantity($item->suggested_qty, $item->unit?->is_fractional));
                        }
                        $orderMessage = __('inventory.order_message').$orderItems->implode(', ');
                    @endphp
                    <div class="p-3 border-bottom bg-light">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="fw-bold text-dark">
                                <i class="bi bi-truck me-1"></i>
                                {{ $supplier?->name ?? __('inventory.no_supplier_known') }}
                            </div>
                            @if($canOrder && (can_route('suppliers.index') || can_route('purchases.create')))
                            <a href="https://wa.me/977{{ \App\Support\NepaliPhone::normalize($supplier->phone) }}?text={{ urlencode($orderMessage) }}" target="_blank" rel="noopener" class="btn btn-success btn-sm">
                                <i class="bi bi-whatsapp me-1"></i>{{ __('inventory.order_whatsapp') }}
                            </a>
                            @endif
                        </div>
                    </div>
                <!-- Desktop Table -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">{{ __('inventory.product') }}</th>
                                <th>{{ __('products.category') }}</th>
                                <th class="text-end">{{ __('products.selling_price') }}</th>
                                <th class="text-center">{{ __('inventory.current_quantity') }}</th>
                                <th class="text-center">{{ __('inventory.min_alert') }}</th>
                                <th class="text-center">{{ __('inventory.suggested_qty') }}</th>
                                <th class="text-center">{{ __('inventory.status') }}</th>
                                <th class="text-end pe-3">{{ __('products.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $product)
                            @php
                                $stock = $product->current_stock ?? '0.000';
                                $minAlert = (string) $product->low_stock_threshold;
                                $isOut = \App\Support\Money::compare($stock, '0', 3) <= 0;
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">{{ $product->name }}</div>
                                    <small class="text-muted"><code>{{ $product->sku ?: __('ui.no_sku') }}</code></small>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $product->category?->name ?? 'General' }}</span></td>
                                <td class="text-end fw-semibold">@npr($product->selling_price)</td>
                                <td class="text-center">
                                    <span class="badge {{ $isOut ? 'bg-danger' : 'bg-warning text-dark' }} px-3 py-2 fs-6">
                                        @qty($stock, $product->unit?->is_fractional) {{ $product->unit?->symbol }}
                                    </span>
                                </td>
                                <td class="text-center text-muted fw-semibold">
                                    @qty($minAlert, $product->unit?->is_fractional) {{ $product->unit?->symbol }}
                                </td>
                                <td class="text-center fw-bold">
                                    @qty($product->suggested_qty, $product->unit?->is_fractional) {{ $product->unit?->symbol }}
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $isOut ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-dark' }}">
                                        {{ $isOut ? __('inventory.out_of_stock') : __('inventory.low_stock') }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('stock.history', $product) }}" class="btn btn-outline-info" title="{{ __('inventory.history_btn') }}">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        @canRoute('purchases.create')
                                        <a href="{{ route('purchases.create') }}" class="btn btn-primary" title="{{ __('inventory.reorder_btn') }}">
                                            <i class="bi bi-cart-plus me-1"></i>{{ __('inventory.reorder_btn') }}
                                        </a>
                                        @endCanRoute
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="d-md-none divide-y">
                    @foreach($items as $product)
                    @php
                        $stock = $product->current_stock ?? '0.000';
                        $minAlert = (string) $product->low_stock_threshold;
                        $isOut = \App\Support\Money::compare($stock, '0', 3) <= 0;
                    @endphp
                    <div class="p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">{{ $product->name }}</h6>
                                <small class="text-muted"><code>{{ $product->sku ?: __('ui.no_sku') }}</code></small>
                            </div>
                            <span class="badge {{ $isOut ? 'bg-danger' : 'bg-warning text-dark' }} px-2 py-1">
                                @qty($stock, $product->unit?->is_fractional) / @qty($minAlert, $product->unit?->is_fractional) {{ $product->unit?->symbol }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <span class="badge {{ $isOut ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-dark' }}">
                                {{ $isOut ? __('inventory.out_of_stock') : __('inventory.low_stock') }}
                            </span>
                            <span class="fw-semibold small">{{ __('inventory.suggested_qty') }}: @qty($product->suggested_qty, $product->unit?->is_fractional) {{ $product->unit?->symbol }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="small text-muted">{{ __('inventory.supplier') }}: {{ $product->preferred_supplier?->name ?? __('inventory.no_supplier_known') }}</span>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('stock.history', $product) }}" class="btn btn-outline-info btn-sm">{{ __('inventory.history_btn') }}</a>
                                @canRoute('purchases.create')
                                <a href="{{ route('purchases.create') }}" class="btn btn-primary btn-sm">{{ __('inventory.reorder_btn') }}</a>
                                @endCanRoute
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
@endsection
