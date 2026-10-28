@extends('layouts.app')

@section('title', __('suppliers.add_supplier') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0" style="max-width: 800px;">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-plus-circle me-2"></i>{{ __('suppliers.create_supplier') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('suppliers.subtitle') }}</p>
        </div>
        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>{{ __('common.back') }}
        </a>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('suppliers.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="name" class="form-label fw-semibold">{{ __('suppliers.name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus placeholder="e.g. Chaudhary Distributors">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="contact_person" class="form-label fw-semibold">{{ __('suppliers.contact_person') }}</label>
                        <input type="text" name="contact_person" id="contact_person" class="form-control @error('contact_person') is-invalid @enderror" value="{{ old('contact_person') }}" placeholder="e.g. Ramesh Shrestha">
                        @error('contact_person')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="phone" class="form-label fw-semibold">{{ __('suppliers.phone') }}</label>
                        <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" placeholder="e.g. 9841234567">
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="email" class="form-label fw-semibold">{{ __('suppliers.email') }}</label>
                        <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="supplier@example.com">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="pan_vat" class="form-label fw-semibold">{{ __('suppliers.pan_vat') }}</label>
                        <input type="text" name="pan_vat" id="pan_vat" class="form-control @error('pan_vat') is-invalid @enderror" value="{{ old('pan_vat') }}" placeholder="9-digit PAN">
                        @error('pan_vat')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="opening_balance" class="form-label fw-semibold">{{ __('suppliers.opening_balance') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">NPR</span>
                            <input type="number" step="0.01" name="opening_balance" id="opening_balance" class="form-control @error('opening_balance') is-invalid @enderror" value="{{ old('opening_balance', '0.00') }}">
                        </div>
                        @error('opening_balance')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="address" class="form-label fw-semibold">{{ __('suppliers.address') }}</label>
                        <input type="text" name="address" id="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address') }}" placeholder="e.g. Kalimati, Kathmandu">
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                    <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-circle me-1"></i>{{ __('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
