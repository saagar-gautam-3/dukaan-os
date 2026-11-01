@extends('layouts.app')

@section('title', __('products.title') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-box-seam me-2"></i>{{ __('products.title') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('products.subtitle') }}</p>
        </div>

        <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
            <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-tags"></i>
                <span>{{ __('products.categories') }}</span>
            </a>
            <a href="{{ route('units.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-rulers"></i>
                <span>{{ __('products.units') }}</span>
            </a>
            @can('products.manage')
            <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-plus-circle"></i>
                <span>{{ __('products.add_product') }}</span>
            </a>
            @endcan
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('products.index') }}" class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="{{ __('products.search_placeholder') }}" value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <select name="category_id" class="form-select">
                        <option value="">{{ __('products.all_categories') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ (string) request('category_id') === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>{{ __('products.filter') }}
                    </button>
                    @if(request()->hasAny(['search', 'category_id']))
                        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary" title="{{ __('products.reset') }}">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Products List -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            @if($products->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-box fs-1 d-block mb-2 text-muted opacity-50"></i>
                    <p class="mb-0">{{ __('products.no_products_found') }}</p>
                </div>
            @else
                <!-- Desktop Table View -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">{{ __('products.name') }}</th>
                                <th>{{ __('products.sku') }}</th>
                                <th>{{ __('products.category') }}</th>
                                <th>{{ __('products.unit') }}</th>
                                <th class="text-end">{{ __('products.cost_price') }}</th>
                                <th class="text-end">{{ __('products.selling_price') }}</th>
                                <th class="text-center">{{ __('products.current_stock') }}</th>
                                <th class="text-center">{{ __('products.status') }}</th>
                                <th class="text-end pe-3">{{ __('products.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $product)
                            @php
                                $stock = $product->stockBalances->reduce(fn (string $sum, $balance): string => \App\Support\Money::add($sum, (string) $balance->quantity, 3), '0.000');
                                $isLowStock = \App\Support\Money::compare((string) $product->min_stock_alert, '0', 3) > 0 && \App\Support\Money::compare($stock, (string) $product->min_stock_alert, 3) <= 0;
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold text-dark">{{ $product->name }}</div>
                                    @if($product->barcode)
                                        <small class="text-muted"><i class="bi bi-upc me-1"></i>{{ $product->barcode }}</small>
                                    @endif
                                </td>
                                <td><code>{{ $product->sku ?: '—' }}</code></td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $product->category?->name ?? __('ui.uncategorized') }}</span>
                                </td>
                                <td>{{ $product->unit?->name }} ({{ $product->unit?->symbol }})</td>
                                <td class="text-end text-muted">@npr($product->cost_price)</td>
                                <td class="text-end fw-bold text-success">@npr($product->selling_price)</td>
                                <td class="text-center">
                                    <span class="badge {{ \App\Support\Money::compare($stock, '0', 3) <= 0 ? 'bg-danger' : ($isLowStock ? 'bg-warning text-dark' : 'bg-success') }} px-2 py-1">
                                        @qty($stock, $product->unit?->is_fractional) {{ $product->unit?->symbol }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $product->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                        {{ $product->is_active ? __('products.active') : __('products.inactive') }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('stock.history', $product) }}" class="btn btn-outline-info" title="{{ __('inventory.stock_movements') }}">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        @can('products.manage')
                                        <a href="{{ route('products.edit', $product) }}" class="btn btn-outline-primary" title="{{ __('products.edit_product') }}">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="{{ route('products.destroy', $product) }}" class="d-inline" onsubmit="return confirm(@json(__('ui.delete_product_confirmation', ['product' => $product->name])))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="{{ __('common.delete') }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View (Mobile-First) -->
                <div class="d-md-none divide-y">
                    @foreach($products as $product)
                    @php
                        $stock = $product->stockBalances->reduce(fn (string $sum, $balance): string => \App\Support\Money::add($sum, (string) $balance->quantity, 3), '0.000');
                        $isLowStock = \App\Support\Money::compare((string) $product->min_stock_alert, '0', 3) > 0 && \App\Support\Money::compare($stock, (string) $product->min_stock_alert, 3) <= 0;
                    @endphp
                    <div class="p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">{{ $product->name }}</h6>
                                <small class="text-muted"><code>{{ $product->sku ?: __('ui.no_sku') }}</code> &bull; {{ $product->category?->name ?? __('ui.general') }}</small>
                            </div>
                            <span class="badge {{ \App\Support\Money::compare($stock, '0', 3) <= 0 ? 'bg-danger' : ($isLowStock ? 'bg-warning text-dark' : 'bg-success') }} px-2 py-1">
                                @qty($stock, $product->unit?->is_fractional) {{ $product->unit?->symbol }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center my-2">
                            <div>
                                <span class="text-muted small">{{ __('products.selling_price') }}:</span>
                                <span class="fw-bold text-success fs-6">@npr($product->selling_price)</span>
                            </div>
                            <div>
                                <span class="text-muted small">{{ __('products.cost_price') }}:</span>
                                <span class="text-muted">@npr($product->cost_price)</span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top">
                            <span class="badge {{ $product->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                {{ $product->is_active ? __('products.active') : __('products.inactive') }}
                            </span>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('stock.history', $product) }}" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-clock-history me-1"></i>{{ __('inventory.stock_movements') }}
                                </a>
                                @can('products.manage')
                                <a href="{{ route('products.edit', $product) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Server-Side Pagination -->
                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <div>
                        {{ $products->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
