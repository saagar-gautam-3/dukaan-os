@extends('layouts.app')

@section('title', __('inventory.low_stock_alerts') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>{{ __('inventory.low_stock_alerts') }}
            </h2>
            <p class="text-muted mb-0 small">Products that have reached or fallen below their reorder thresholds</p>
        </div>

        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>{{ __('common.back') }}
            </a>
            @can('purchases.manage')
            <a href="{{ route('purchases.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-cart-plus me-1"></i>{{ __('purchases.create_purchase') }}
            </a>
            @endcan
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                Alert Items ({{ $products->count() }})
            </h5>
        </div>
        <div class="card-body p-0">
            @if($products->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-check-circle text-success fs-1 d-block mb-2"></i>
                    <h5 class="fw-bold text-dark">All Stock Levels Healthy!</h5>
                    <p class="mb-0 text-muted">{{ __('inventory.no_alerts') }}</p>
                </div>
            @else
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
                                <th class="text-center">{{ __('inventory.status') }}</th>
                                <th class="text-end pe-3">{{ __('products.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $product)
                            @php
                                $stock = (float) $product->stockBalances->sum('quantity');
                                $minAlert = (float) $product->min_stock_alert;
                                $isOut = $stock <= 0;
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">{{ $product->name }}</div>
                                    <small class="text-muted"><code>{{ $product->sku ?: 'No SKU' }}</code></small>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $product->category?->name ?? 'General' }}</span></td>
                                <td class="text-end fw-semibold">@npr($product->selling_price)</td>
                                <td class="text-center">
                                    <span class="badge {{ $isOut ? 'bg-danger' : 'bg-warning text-dark' }} px-3 py-2 fs-6">
                                        {{ number_format($stock, $product->unit?->is_fractional ? 3 : 0) }} {{ $product->unit?->symbol }}
                                    </span>
                                </td>
                                <td class="text-center text-muted fw-semibold">
                                    {{ number_format($minAlert, 3) }} {{ $product->unit?->symbol }}
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $isOut ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-dark' }}">
                                        {{ $isOut ? __('inventory.out_of_stock') : __('inventory.low_stock') }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('stock.history', $product) }}" class="btn btn-outline-info" title="History">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        @can('purchases.manage')
                                        <a href="{{ route('purchases.create') }}" class="btn btn-primary" title="Create Purchase">
                                            <i class="bi bi-cart-plus me-1"></i>Reorder
                                        </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="d-md-none divide-y">
                    @foreach($products as $product)
                    @php
                        $stock = (float) $product->stockBalances->sum('quantity');
                        $minAlert = (float) $product->min_stock_alert;
                        $isOut = $stock <= 0;
                    @endphp
                    <div class="p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">{{ $product->name }}</h6>
                                <small class="text-muted"><code>{{ $product->sku ?: 'No SKU' }}</code></small>
                            </div>
                            <span class="badge {{ $isOut ? 'bg-danger' : 'bg-warning text-dark' }} px-2 py-1">
                                {{ number_format($stock, $product->unit?->is_fractional ? 3 : 0) }} / {{ number_format($minAlert, $product->unit?->is_fractional ? 3 : 0) }} {{ $product->unit?->symbol }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <span class="badge {{ $isOut ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-dark' }}">
                                {{ $isOut ? __('inventory.out_of_stock') : __('inventory.low_stock') }}
                            </span>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('stock.history', $product) }}" class="btn btn-outline-info btn-sm">History</a>
                                @can('purchases.manage')
                                <a href="{{ route('purchases.create') }}" class="btn btn-primary btn-sm">Reorder</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
