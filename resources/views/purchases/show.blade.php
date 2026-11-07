@extends('layouts.app')

@section('title', __('purchases.purchase_no') . ' #' . $purchase->purchase_no . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h2 class="fw-bold mb-0" style="color: #1a3a5c;">
                    <i class="bi bi-receipt me-2"></i>{{ __('ui.purchase_number') }}{{ $purchase->purchase_no }}
                </h2>
                <span class="badge {{ $purchase->isPosted() ? 'bg-success fs-6' : 'bg-warning text-dark fs-6' }} px-3 py-1">
                    {{ $purchase->isPosted() ? __('purchases.status_posted') : __('purchases.status_draft') }}
                </span>
            </div>
            <p class="text-muted mb-0 small">
                {{ __('purchases.purchase_date') }}: <span class="fw-medium text-dark">{{ $purchase->purchase_date?->format('Y-m-d') }}</span> &bull;
                {{ __('purchases.created_by') }}: <span class="fw-medium text-dark">{{ $purchase->createdBy?->name ?? __('ui.system') }}</span>
                @if($purchase->posted_at)
                    &bull; {{ __('purchases.posted_at') }}: <span class="fw-medium text-dark">{{ $purchase->posted_at->format('Y-m-d H:i') }}</span>
                @endif
            </p>
        </div>

        <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
            <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>{{ __('common.back') }}
            </a>
            @if(! $purchase->isPosted())
                @canRoute('purchases.post', $purchase)
                <form method="POST" action="{{ route('purchases.post', $purchase) }}" class="d-inline" onsubmit="return confirm('{{ __('purchases.post_confirmation') }}')">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm px-3">
                        <i class="bi bi-box-arrow-in-down me-1"></i>{{ __('purchases.post_purchase') }}
                    </button>
                </form>
                @endCanRoute
                @canRoute('purchases.destroy', $purchase)
                <form method="POST" action="{{ route('purchases.destroy', $purchase) }}" class="d-inline" onsubmit="return confirm('{{ __('purchases.confirm_delete') }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>{{ __('purchases.delete_draft') }}
                    </button>
                </form>
                @endCanRoute
            @endif
        </div>
    </div>

    <!-- Posted Immutable Banner -->
    @if($purchase->isPosted())
        <div class="alert alert-info border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center" role="alert">
            <i class="bi bi-lock-fill fs-3 text-info me-3"></i>
            <div>
                <h6 class="fw-bold mb-1 text-info-emphasis">{{ __('purchases.immutable_heading') }}</h6>
                <p class="mb-0 small text-muted">{{ __('purchases.immutable_notice') }}</p>
            </div>
        </div>
    @else
        <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center" role="alert">
            <i class="bi bi-hourglass-split fs-3 text-warning me-3"></i>
            <div>
                <h6 class="fw-bold mb-1 text-dark">{{ __('purchases.draft_notice') }}</h6>
                <p class="mb-0 small text-muted">{{ __('purchases.draft_notice_desc', ['action' => __('purchases.post_purchase')]) }}</p>
            </div>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <!-- Supplier Details -->
        <div class="col-12 col-md-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-truck me-2 text-primary"></i>{{ __('purchases.supplier') }}
                    </h6>
                </div>
                <div class="card-body">
                    <h5 class="fw-bold text-dark mb-1">{{ $purchase->supplier->name }}</h5>
                    @if($purchase->supplier->contact_person)
                        <div class="text-muted small mb-1">{{ $purchase->supplier->contact_person }}</div>
                    @endif
                    @if($purchase->supplier->phone)
                        <div class="small"><i class="bi bi-telephone me-1 text-primary"></i>{{ $purchase->supplier->phone }}</div>
                    @endif
                    @if($purchase->supplier->pan_vat)
                        <div class="small text-muted">{{ __('ui.pan_label') }} <code>{{ $purchase->supplier->pan_vat }}</code></div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Warehouse & Delivery -->
        <div class="col-12 col-md-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-building me-2 text-primary"></i>{{ __('purchases.receiving_loc') }}
                    </h6>
                </div>
                <div class="card-body">
                    <h5 class="fw-bold text-dark mb-1">{{ $purchase->warehouse->name }}</h5>
                    <div class="text-muted small mb-2">{{ __('purchases.warehouse_code') }}: <code>{{ $purchase->warehouse->code ?: __('ui.not_available') }}</code></div>
                    @if($purchase->notes)
                        <div class="p-2 bg-light rounded small text-muted">
                            <strong>{{ __('purchases.notes') }}:</strong> {{ $purchase->notes }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="fw-bold mb-0 text-dark">{{ __('purchases.items') }} ({{ $purchase->items->count() }})</h5>
        </div>
        <div class="card-body p-0">
            <!-- Desktop Table -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">{{ __('purchases.product') }}</th>
                            <th class="text-center">{{ __('purchases.quantity') }}</th>
                            <th class="text-end">{{ __('purchases.unit_cost') }}</th>
                            <th class="text-end">{{ __('purchases.discount') }}</th>
                            <th class="text-end pe-3">{{ __('purchases.line_total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchase->items as $item)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold text-dark">{{ $item->product->name }}</div>
                                <small class="text-muted"><code>{{ $item->product->sku ?: __('purchases.no_sku') }}</code></small>
                            </td>
                            <td class="text-center fw-bold">
                                @qty($item->quantity, $item->product->unit?->is_fractional) {{ $item->product->unit?->symbol }}
                            </td>
                            <td class="text-end text-muted">@npr($item->unit_cost)</td>
                            <td class="text-end text-danger">- @npr($item->discount)</td>
                            <td class="text-end fw-bold text-dark pe-3">@npr($item->line_total)</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="d-md-none divide-y">
                @foreach($purchase->items as $item)
                <div class="p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">{{ $item->product->name }}</h6>
                            <small class="text-muted"><code>{{ $item->product->sku ?: __('purchases.no_sku') }}</code></small>
                        </div>
                        <span class="fw-bold text-dark">
                            @qty($item->quantity, $item->product->unit?->is_fractional) {{ $item->product->unit?->symbol }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 small">
                        <span class="text-muted">{{ __('purchases.rate') }}: @npr($item->unit_cost)</span>
                        <span class="fw-bold text-success fs-6">@npr($item->line_total)</span>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Summary -->
            <div class="p-3 bg-light border-top">
                <div class="row justify-content-end">
                    <div class="col-12 col-md-5">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">{{ __('purchases.subtotal') }}:</span>
                            <span class="fw-semibold text-dark">@npr($purchase->subtotal)</span>
                        </div>
                        @if(\App\Support\Money::compare((string) $purchase->discount_total, '0', 2) > 0)
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">{{ __('purchases.discount') }}:</span>
                            <span class="text-danger">- @npr($purchase->discount_total)</span>
                        </div>
                        @endif
                        <div class="d-flex justify-content-between border-top pt-2">
                            <span class="fw-bold fs-5 text-dark">{{ __('purchases.grand_total') }}:</span>
                            <span class="fw-bold fs-5 text-success">@npr($purchase->grand_total)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
