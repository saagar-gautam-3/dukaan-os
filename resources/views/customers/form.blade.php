@extends('layouts.app')

@section('title', isset($customer) ? ('Edit ' . $customer->name) : __('sales.new_customer'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ isset($customer) ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 fw-bold mb-0">{{ isset($customer) ? ('Edit Customer: ' . $customer->name) : __('sales.new_customer') }}</h1>
    </div>

    <div class="card border-0 shadow-sm" style="max-width: 720px;">
        <div class="card-body p-4">
            <form action="{{ isset($customer) ? route('customers.update', $customer) : route('customers.store') }}" method="POST">
                @csrf
                @isset($customer)
                    @method('PUT')
                @endisset

                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('sales.customer_name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                           value="{{ old('name', $customer->name ?? '') }}" required autofocus placeholder="e.g. Ram Bahadur Shrestha">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ __('sales.phone') }}</label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" 
                               value="{{ old('phone', $customer->phone ?? '') }}" placeholder="98XXXXXXXX">
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                               value="{{ old('email', $customer->email ?? '') }}" placeholder="customer@example.com">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('sales.address') }}</label>
                    <input type="text" name="address" class="form-control @error('address') is-invalid @enderror" 
                               value="{{ old('address', $customer->address ?? '') }}" placeholder="e.g. Asan, Kathmandu">
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">PAN / VAT Number</label>
                        <input type="text" name="pan_no" class="form-control @error('pan_no') is-invalid @enderror" 
                               value="{{ old('pan_no', $customer->pan_no ?? '') }}" placeholder="9-digit PAN">
                        @error('pan_no')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Credit Limit (Rs.)</label>
                        <input type="number" step="0.01" min="0" name="credit_limit" class="form-control @error('credit_limit') is-invalid @enderror" 
                               value="{{ old('credit_limit', $customer->credit_limit ?? '0.00') }}">
                        @error('credit_limit')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    @isset($customer)
                        @can('delete', $customer)
                        <button type="button" class="btn btn-outline-danger" 
                                onclick="if(confirm('Are you sure you want to delete this customer?')) document.getElementById('deleteCustomerForm').submit();">
                            <i class="bi bi-trash3 me-1"></i> Delete
                        </button>
                        @else
                        <div></div>
                        @endcan
                    @else
                        <div></div>
                    @endisset

                    <div class="d-flex gap-2">
                        <a href="{{ isset($customer) ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary fw-semibold">
                            <i class="bi bi-save me-1"></i> {{ isset($customer) ? 'Update Customer' : 'Save Customer' }}
                        </button>
                    </div>
                </div>
            </form>

            @isset($customer)
                @can('delete', $customer)
                <form id="deleteCustomerForm" action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
                @endcan
            @endisset
        </div>
    </div>
</div>
@endsection
