@extends('layouts.app')

@section('title', __('sales.customers'))

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1">{{ __('sales.customers') }}</h1>
            <p class="text-muted small mb-0">{{ __('ui.customer_directory') }}</p>
        </div>
        <div>
            @can('create', \App\Models\Customer::class)
            <a href="{{ route('customers.create') }}" class="btn btn-primary d-flex align-items-center">
                <i class="bi bi-person-plus me-1"></i> {{ __('sales.new_customer') }}
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

    <!-- Search -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="{{ route('customers.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-6 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-0" 
                               placeholder="{{ __('ui.search_customer_placeholder') }}" value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-6 col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-search me-1"></i>{{ __('ui.search') }}
                    </button>
                    @if(request('search'))
                    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>{{ __('ui.clear') }}
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('sales.customer_name') }}</th>
                        <th>{{ __('sales.phone') }}</th>
                        <th>{{ __('sales.address') }}</th>
                        <th>{{ __('ui.customer_pan_vat') }}</th>
                        <th class="text-center">{{ __('ui.total_bills') }}</th>
                        <th class="text-end" style="width: 120px;">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr>
                        <td>
                            <a href="{{ route('customers.show', $customer) }}" class="fw-bold text-dark text-decoration-none">
                                {{ $customer->name }}
                            </a>
                        </td>
                        <td>{{ $customer->phone ?: '-' }}</td>
                        <td>{{ $customer->address ?: '-' }}</td>
                        <td>{{ $customer->pan_no ?: '-' }}</td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border">{{ $customer->sales_count }}</span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary" title="{{ __('ui.view_details') }}">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('update', $customer)
                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-secondary" title="{{ __('ui.edit') }}">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                            <p class="mb-0">{{ __('sales.no_customers') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($customers->hasPages())
        <div class="card-footer bg-white border-top p-3">
            {{ $customers->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
