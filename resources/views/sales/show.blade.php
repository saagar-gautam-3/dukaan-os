@extends('layouts.app')

@section('title', __('sales.sale') . ' ' . ($sale->invoice_no ?? '#' . $sale->id))

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h1 class="h3 fw-bold mb-0">{{ $sale->invoice_no ?? ('Draft #' . $sale->id) }}</h1>
                    @if($sale->isPosted())
                        <span class="badge bg-success-subtle text-success border border-success-subtle">{{ __('sales.status_posted') }}</span>
                    @elseif($sale->isDraft())
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">{{ __('sales.status_draft') }}</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary border">{{ __('sales.status_cancelled') }}</span>
                    @endif
                </div>
                <small class="text-muted">{{ __('sales.date') }}: {{ $sale->created_at->format('Y-m-d H:i') }}</small>
            </div>
        </div>

        <div class="d-flex gap-2">
            @if($sale->isPosted())
                <a href="{{ route('sales.invoice', $sale) }}" target="_blank" class="btn btn-outline-dark">
                    <i class="bi bi-printer me-1"></i> {{ __('sales.print_invoice') }}
                </a>
                <a href="{{ route('sales.thermal', $sale) }}" target="_blank" class="btn btn-outline-dark">
                    <i class="bi bi-receipt me-1"></i> {{ __('sales.thermal_print') }}
                </a>
                @can('return', $sale)
                <a href="{{ route('sales.return', $sale) }}" class="btn btn-outline-warning">
                    <i class="bi bi-arrow-return-left me-1"></i> {{ __('sales.return_sale') }}
                </a>
                @endcan
            @elseif($sale->isDraft())
                @can('post', $sale)
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#postSaleModal">
                    <i class="bi bi-check2-circle me-1"></i> {{ __('sales.post_sale') }}
                </button>
                @endcan
                @can('delete', $sale)
                <form action="{{ route('sales.cancel', $sale) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this draft sale?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-x-circle me-1"></i> Cancel Draft
                    </button>
                </form>
                @endcan
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row g-4">
        <!-- Main: Items Table -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="mb-0 fw-bold">Sale Items</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-center">{{ __('sales.qty') }}</th>
                                <th class="text-end">{{ __('sales.price') }}</th>
                                @can('reports.view')
                                <th class="text-end text-muted">Cost Snapshot</th>
                                @endcan
                                <th class="text-end">{{ __('sales.discount') }}</th>
                                <th class="text-end">{{ __('sales.line_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sale->items as $item)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item->product->name }}</div>
                                    <small class="text-muted">SKU: {{ $item->product->sku }}</small>
                                </td>
                                <td class="text-center fw-semibold">
                                    {{ (float) $item->quantity }} {{ $item->product->unit?->symbol }}
                                </td>
                                <td class="text-end">
                                    Rs. {{ number_format((float) $item->unit_price, 2) }}
                                </td>
                                @can('reports.view')
                                <td class="text-end text-muted small">
                                    Rs. {{ number_format((float) $item->unit_cost, 2) }}
                                </td>
                                @endcan
                                <td class="text-end text-muted">
                                    Rs. {{ number_format((float) $item->discount, 2) }}
                                </td>
                                <td class="text-end fw-bold">
                                    Rs. {{ number_format((float) $item->line_total, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payment Allocations -->
            @if($sale->paymentAllocations->isNotEmpty())
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-wallet2 text-success me-2"></i>Payment History</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Date</th>
                                <th class="text-end">Amount Allocated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sale->paymentAllocations as $alloc)
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border text-uppercase">
                                        {{ $alloc->payment->method instanceof \BackedEnum ? $alloc->payment->method->value : $alloc->payment->method }}
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    {{ $alloc->payment->reference_number ?? '-' }}
                                </td>
                                <td class="small">
                                    {{ $alloc->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="text-end fw-bold text-success">
                                    Rs. {{ number_format((float) $alloc->amount, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Returns History -->
            @if($sale->returns->isNotEmpty())
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="mb-0 fw-bold text-danger"><i class="bi bi-arrow-return-left me-2"></i>Returns</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Return No.</th>
                                <th>Date</th>
                                <th>Reason</th>
                                <th class="text-end">Total Refunded</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sale->returns as $ret)
                            <tr>
                                <td class="fw-bold">{{ $ret->return_no }}</td>
                                <td class="small">{{ $ret->created_at->format('Y-m-d H:i') }}</td>
                                <td class="text-muted small">{{ $ret->reason ?: '-' }}</td>
                                <td class="text-end fw-bold text-danger">Rs. {{ number_format((float) $ret->total, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar Details -->
        <div class="col-lg-4">
            <!-- Totals Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="mb-0 fw-bold">Summary</h5>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between mb-2 text-muted">
                        <span>{{ __('sales.subtotal') }}:</span>
                        <span>Rs. {{ number_format((float) $sale->subtotal, 2) }}</span>
                    </div>
                    @if((float) $sale->discount_total > 0)
                    <div class="d-flex justify-content-between mb-2 text-danger">
                        <span>{{ __('sales.discount') }}:</span>
                        <span>-Rs. {{ number_format((float) $sale->discount_total, 2) }}</span>
                    </div>
                    @endif
                    @if((float) $sale->tax_total > 0)
                    <div class="d-flex justify-content-between mb-2 text-muted">
                        <span>{{ __('sales.tax') }}:</span>
                        <span>+Rs. {{ number_format((float) $sale->tax_total, 2) }}</span>
                    </div>
                    @endif
                    <div class="d-flex justify-content-between mb-3 fs-5 fw-bold text-dark border-top pt-2">
                        <span>{{ __('sales.grand_total') }}:</span>
                        <span class="text-primary">Rs. {{ number_format((float) $sale->grand_total, 2) }}</span>
                    </div>

                    <div class="border-top pt-2">
                        <div class="d-flex justify-content-between mb-1 text-success fw-semibold">
                            <span>{{ __('sales.paid') }}:</span>
                            <span>Rs. {{ number_format((float) $sale->paid_total, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold {{ (float) $sale->dueAmount() > 0 ? 'text-danger' : 'text-muted' }}">
                            <span>{{ __('sales.due') }}:</span>
                            <span>Rs. {{ number_format((float) $sale->dueAmount(), 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h6 class="mb-0 fw-bold">{{ __('sales.customer') }}</h6>
                </div>
                <div class="card-body p-3">
                    @if($sale->customer)
                        <h6 class="fw-bold mb-1">{{ $sale->customer->name }}</h6>
                        @if($sale->customer->phone)
                            <p class="mb-1 text-muted small"><i class="bi bi-telephone me-1"></i>{{ $sale->customer->phone }}</p>
                        @endif
                        @if($sale->customer->address)
                            <p class="mb-2 text-muted small"><i class="bi bi-geo-alt me-1"></i>{{ $sale->customer->address }}</p>
                        @endif
                        <a href="{{ route('customers.show', $sale->customer) }}" class="btn btn-sm btn-outline-primary">
                            View Customer Ledger
                        </a>
                    @else
                        <p class="mb-0 text-muted fst-italic">{{ __('sales.walk_in') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Post Draft Modal -->
@if($sale->isDraft())
<div class="modal fade" id="postSaleModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('sales.post', $sale) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">{{ __('sales.post_sale') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        Posting this sale will deduct inventory from stock and assign an official sequential invoice number.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('sales.payment_method') }}</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash">{{ __('sales.cash') }}</option>
                            <option value="qr">{{ __('sales.qr') }}</option>
                            <option value="bank">{{ __('sales.bank') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('sales.amount_paid') }}</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" name="payment_amount" step="0.01" min="0" class="form-control" value="{{ $sale->grand_total }}">
                        </div>
                        <small class="text-muted">Total payable: Rs. {{ number_format((float) $sale->grand_total, 2) }}</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">Confirm & Post</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
