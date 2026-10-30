@extends('layouts.app')

@section('title', __('purchases.title') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-cart-check me-2"></i>{{ __('purchases.title') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('purchases.subtitle') }}</p>
        </div>

        @can('purchases.manage')
        <div class="mt-3 mt-md-0">
            <a href="{{ route('purchases.create') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-plus-circle"></i>
                <span>{{ __('purchases.create_purchase') }}</span>
            </a>
        </div>
        @endcan
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('purchases.index') }}" class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search bill no or supplier..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <select name="supplier_id" class="form-select">
                        <option value="">All Suppliers</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ (string) request('supplier_id') === (string) $supplier->id ? 'selected' : '' }}>
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('purchases.status_draft') }}</option>
                        <option value="posted" {{ request('status') === 'posted' ? 'selected' : '' }}>{{ __('purchases.status_posted') }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    @if(request()->hasAny(['search', 'supplier_id', 'status']))
                        <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Purchases List -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            @if($purchases->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-cart-x fs-1 d-block mb-2 text-muted opacity-50"></i>
                    <p class="mb-0">{{ __('purchases.no_purchases_found') }}</p>
                </div>
            @else
                <!-- Desktop Table -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">{{ __('purchases.purchase_no') }}</th>
                                <th>{{ __('purchases.purchase_date') }}</th>
                                <th>{{ __('purchases.supplier') }}</th>
                                <th>{{ __('purchases.warehouse') }}</th>
                                <th class="text-end">{{ __('purchases.grand_total') }}</th>
                                <th class="text-center">{{ __('purchases.status') }}</th>
                                <th class="text-end pe-3">{{ __('products.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchases as $purchase)
                            @php
                                $isPosted = $purchase->isPosted();
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('purchases.show', $purchase) }}" class="fw-bold text-decoration-none text-dark">
                                        #{{ $purchase->purchase_no }}
                                    </a>
                                </td>
                                <td>{{ $purchase->purchase_date?->format('Y-m-d') }}</td>
                                <td>
                                    <span class="fw-semibold">{{ $purchase->supplier->name }}</span>
                                </td>
                                <td><i class="bi bi-building me-1 text-secondary"></i>{{ $purchase->warehouse->name }}</td>
                                <td class="text-end fw-bold text-dark">@npr($purchase->grand_total)</td>
                                <td class="text-center">
                                    <span class="badge {{ $isPosted ? 'bg-success' : 'bg-warning text-dark' }} px-2 py-1">
                                        {{ $isPosted ? __('purchases.status_posted') : __('purchases.status_draft') }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-outline-info" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if(! $isPosted)
                                        @can('purchases.manage')
                                        <form method="POST" action="{{ route('purchases.post', $purchase) }}" class="d-inline" onsubmit="return confirm('{{ __('purchases.post_confirmation') }}')">
                                            @csrf
                                            <button type="submit" class="btn btn-success" title="Post & Receive">
                                                <i class="bi bi-box-arrow-in-down me-1"></i>Post
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('purchases.destroy', $purchase) }}" class="d-inline" onsubmit="return confirm('{{ __('purchases.confirm_delete') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="d-md-none divide-y">
                    @foreach($purchases as $purchase)
                    @php
                        $isPosted = $purchase->isPosted();
                    @endphp
                    <div class="p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <a href="{{ route('purchases.show', $purchase) }}" class="fw-bold text-decoration-none text-dark fs-6">
                                    #{{ $purchase->purchase_no }}
                                </a>
                                <div class="text-muted small">{{ $purchase->supplier->name }}</div>
                            </div>
                            <span class="badge {{ $isPosted ? 'bg-success' : 'bg-warning text-dark' }} px-2 py-1">
                                {{ $isPosted ? __('purchases.status_posted') : __('purchases.status_draft') }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center my-2">
                            <span class="text-muted small">{{ $purchase->purchase_date?->format('Y-m-d') }}</span>
                            <span class="fw-bold text-dark fs-6">@npr($purchase->grand_total)</span>
                        </div>
                        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                            <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                            @if(! $isPosted)
                            @can('purchases.manage')
                            <form method="POST" action="{{ route('purchases.post', $purchase) }}" class="d-inline" onsubmit="return confirm('{{ __('purchases.post_confirmation') }}')">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="bi bi-box-arrow-in-down me-1"></i>Post
                                </button>
                            </form>
                            @endcan
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        Showing {{ $purchases->firstItem() ?? 0 }} to {{ $purchases->lastItem() ?? 0 }} of {{ $purchases->total() }} orders
                    </span>
                    <div>
                        {{ $purchases->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
