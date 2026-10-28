@extends('layouts.app')

@section('title', __('reports.stock_valuation'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h1 class="h3 fw-bold mb-0"><i class="bi bi-boxes me-2"></i>{{ __('reports.stock_valuation') }}</h1>
        <div class="text-muted small">Total Value: <strong>Rs {{ number_format($valuation['total_value'], 2) }}</strong></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('reports.product') }}</th>
                        <th>{{ __('reports.sku') }}</th>
                        <th class="text-end">{{ __('reports.quantity') }}</th>
                        <th class="text-end">{{ __('reports.avg_cost') }}</th>
                        <th class="text-end">{{ __('reports.stock_value') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($valuation['items'] as $item)
                    <tr>
                        <td class="fw-semibold">{{ $item->name }}</td>
                        <td class="text-muted small">{{ $item->sku ?? '—' }}</td>
                        <td class="text-end">{{ number_format($item->qty, 2) }}</td>
                        <td class="text-end">Rs {{ number_format($item->avg_cost, 2) }}</td>
                        <td class="text-end fw-semibold">Rs {{ number_format($item->value, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="bi bi-boxes fs-2 d-block mb-2"></i>
                            No tracked stock found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if(count($valuation['items']) > 0)
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">{{ __('reports.total_value') }}</td>
                        <td class="text-end">Rs {{ number_format($valuation['total_value'], 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection

