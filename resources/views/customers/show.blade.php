@extends('layouts.app')

@section('title', $customer->name)

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h3 fw-bold mb-0">{{ $customer->name }}</h1>
                <small class="text-muted">Customer since {{ $customer->created_at->format('M Y') }}</small>
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('update', $customer)
            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row g-4">
        <!-- Customer Details Card -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="mb-0 fw-bold">Contact Details</h5>
                </div>
                <div class="card-body p-3">
                    <div class="mb-2">
                        <small class="text-muted d-block">{{ __('sales.phone') }}</small>
                        <span class="fw-semibold">{{ $customer->phone ?: 'Not provided' }}</span>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted d-block">Email</small>
                        <span>{{ $customer->email ?: 'Not provided' }}</span>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted d-block">{{ __('sales.address') }}</small>
                        <span>{{ $customer->address ?: 'Not provided' }}</span>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted d-block">PAN / VAT</small>
                        <span>{{ $customer->pan_no ?: 'Not provided' }}</span>
                    </div>
                    @if((float) $customer->credit_limit > 0)
                    <div class="mb-0">
                        <small class="text-muted d-block">Credit Limit</small>
                        <span class="fw-bold text-primary">Rs. {{ number_format((float) $customer->credit_limit, 2) }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sales History -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Recent Purchases</h5>
                    @can('sales.create')
                    <a href="{{ route('pos') }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> New Bill
                    </a>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice</th>
                                <th>Date</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Due</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($customer->sales as $sale)
                            <tr>
                                <td>
                                    <a href="{{ route('sales.show', $sale) }}" class="fw-bold text-decoration-none">
                                        {{ $sale->invoice_no ?? ('Draft #' . $sale->id) }}
                                    </a>
                                </td>
                                <td>{{ $sale->created_at->format('Y-m-d') }}</td>
                                <td class="text-end fw-bold">Rs. {{ number_format((float) $sale->grand_total, 2) }}</td>
                                <td class="text-end text-success">Rs. {{ number_format((float) $sale->paid_total, 2) }}</td>
                                <td class="text-end">
                                    @php $due = (float) $sale->dueAmount(); @endphp
                                    @if($due > 0)
                                        <span class="text-danger fw-bold">Rs. {{ number_format($due, 2) }}</span>
                                    @else
                                        <span class="text-muted">Rs. 0.00</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $sale->isPosted() ? 'success' : 'warning' }}-subtle text-{{ $sale->isPosted() ? 'success' : 'warning' }} border">
                                        {{ $sale->status->value }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No sales recorded for this customer yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
