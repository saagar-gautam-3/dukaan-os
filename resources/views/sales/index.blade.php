@extends('layouts.app')

@section('title', __('sales.all_sales'))

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1">{{ __('sales.all_sales') }}</h1>
            <p class="text-muted small mb-0">{{ __('sales.sales') }} {{ __('sales.transactions_desc') }}</p>
        </div>
        <div class="d-flex gap-2">
            @can('sales.create')
            <a href="{{ route('pos') }}" class="btn btn-primary d-flex align-items-center">
                <i class="bi bi-calculator me-1"></i> {{ __('sales.pos_title') }}
            </a>
            @endcan
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="{{ route('sales.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-0" 
                               placeholder="{{ __('sales.search_sales') }}" value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <select name="status" class="form-select bg-light border-0">
                        <option value="">-- {{ __('sales.status') }} (All) --</option>
                        <option value="posted" {{ request('status') === 'posted' ? 'selected' : '' }}>{{ __('sales.status_posted') }}</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('sales.status_draft') }}</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('sales.status_cancelled') }}</option>
                    </select>
                </div>
                <div class="col-md-4 col-6 d-flex gap-2">
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-funnel me-1"></i> {{ __('sales.filter') }}
                    </button>
                    @if(request()->hasAny(['search', 'status']))
                    <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i> {{ __('sales.clear') }}
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Sales Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('sales.invoice_no') }}</th>
                        <th>{{ __('sales.customer') }}</th>
                        <th>{{ __('sales.date') }}</th>
                        <th class="text-end">{{ __('sales.grand_total') }}</th>
                        <th class="text-end">{{ __('sales.paid') }}</th>
                        <th class="text-end">{{ __('sales.due') }}</th>
                        <th class="text-center">{{ __('sales.status') }}</th>
                        <th class="text-end" style="width: 140px;">{{ __('sales.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                    <tr>
                        <td>
                            <a href="{{ route('sales.show', $sale) }}" class="fw-bold text-decoration-none">
                                {{ $sale->invoice_no ?? ('Draft #' . $sale->id) }}
                            </a>
                        </td>
                        <td>
                            @if($sale->customer)
                                <a href="{{ route('customers.show', $sale->customer) }}" class="text-dark text-decoration-none fw-semibold">
                                    {{ $sale->customer->name }}
                                </a>
                                @if($sale->customer->phone)
                                <div class="small text-muted">{{ $sale->customer->phone }}</div>
                                @endif
                            @else
                                <span class="text-muted">{{ __('sales.walk_in') }}</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $sale->created_at->format('Y-m-d') }}</div>
                            <small class="text-muted">{{ $sale->created_at->format('H:i') }}</small>
                        </td>
                        <td class="text-end fw-bold">
                            @npr($sale->grand_total)
                        </td>
                        <td class="text-end text-success fw-semibold">
                            @npr($sale->paid_total)
                        </td>
                        <td class="text-end">
                            @php $due = (float) $sale->dueAmount(); @endphp
                            @if($due > 0)
                                <span class="text-danger fw-bold">@npr($due)</span>
                            @else
                                <span class="text-muted">@npr(0)</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($sale->isPosted())
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                    {{ __('sales.status_posted') }}
                                </span>
                            @elseif($sale->isDraft())
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                    {{ __('sales.status_draft') }}
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                                    {{ __('sales.status_cancelled') }}
                                </span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('sales.show', $sale) }}" class="btn btn-outline-secondary" title="View details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($sale->isPosted())
                                <a href="{{ route('sales.invoice', $sale) }}" class="btn btn-outline-secondary" target="_blank" title="A4 Invoice">
                                    <i class="bi bi-printer"></i>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-receipt-cutoff fs-1 d-block mb-2 opacity-50"></i>
                            <p class="mb-0">{{ __('sales.no_sales') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($sales->hasPages())
        <div class="card-footer bg-white border-top p-3">
            {{ $sales->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
