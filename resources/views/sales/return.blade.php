@extends('layouts.app')

@section('title', __('sales.return_sale') . ' - ' . $sale->invoice_no)

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('sales.show', $sale) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="h3 fw-bold mb-0">{{ __('sales.return_sale') }}</h1>
            <p class="text-muted small mb-0">{{ __('sales.invoice_label') }} {{ $sale->invoice_no }} | {{ __('sales.date_label') }} {{ $sale->created_at->format('Y-m-d') }}</p>
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <form action="{{ route('sales.return.store', $sale) }}" method="POST">
        @csrf
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom p-3">
                <h5 class="mb-0 fw-bold">{{ __('sales.return_items') }}</h5>
                <small class="text-muted">Enter return quantity for items to be returned. Unchanged items with 0 quantity will be skipped.</small>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Item</th>
                            <th class="text-center">{{ __('sales.sold_qty') }}</th>
                            <th class="text-center">{{ __('sales.already_returned') }}</th>
                            <th class="text-end">{{ __('sales.unit_price_col') }}</th>
                            <th style="width: 180px;">{{ __('sales.return_qty') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sale->items as $idx => $item)
                        @php
                            $alreadyReturned = (float) $item->saleReturnItems()->sum('quantity');
                            $maxReturnable = (float) $item->quantity - $alreadyReturned;
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $item->product->name }}</div>
                                <small class="text-muted">SKU: {{ $item->product->sku }}</small>
                                <input type="hidden" name="items[{{ $idx }}][sale_item_id]" value="{{ $item->id }}">
                            </td>
                            <td class="text-center">@qty($item->quantity, $item->product->unit?->is_fractional) {{ $item->product->unit?->symbol }}</td>
                            <td class="text-center text-muted">@qty($alreadyReturned, $item->product->unit?->is_fractional) {{ $item->product->unit?->symbol }}</td>
                            <td class="text-end">@npr($item->unit_price)</td>
                            <td>
                                @if($maxReturnable > 0)
                                <div class="input-group input-group-sm">
                                    <input type="number" name="items[{{ $idx }}][quantity]" 
                                           class="form-control" step="0.001" min="0" max="{{ $maxReturnable }}" 
                                           value="0" placeholder="0">
                                    <span class="input-group-text">{{ $item->product->unit?->symbol }}</span>
                                </div>
                                <small class="text-muted">{{ __('sales.max_label') }} @qty($maxReturnable, $item->product->unit?->is_fractional)</small>
                                @else
                                <span class="badge bg-secondary">{{ __('sales.fully_returned') }}</span>
                                <input type="hidden" name="items[{{ $idx }}][quantity]" value="0">
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('sales.return_reason') }}</label>
                    <textarea name="reason" class="form-control" rows="3" placeholder="Defective product, customer change of mind, expired, etc."></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
                    <button type="submit" class="btn btn-danger fw-bold">
                        <i class="bi bi-arrow-return-left me-1"></i> {{ __('sales.process_return') }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
