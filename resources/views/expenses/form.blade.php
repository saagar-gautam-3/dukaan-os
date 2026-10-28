@extends('layouts.app')

@section('title', isset($expense) ? __('expenses.edit_expense') : __('expenses.new_expense'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 fw-bold mb-0">{{ isset($expense) ? __('expenses.edit_expense') : __('expenses.new_expense') }}</h1>
    </div>

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card border-0 shadow-sm" style="max-width:680px">
        <div class="card-body p-4">
            <form action="{{ isset($expense) ? route('expenses.update', $expense) : route('expenses.store') }}" method="POST">
                @csrf
                @isset($expense)
                    @method('PUT')
                @endisset

                {{-- Category --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('expenses.category') }} <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                        <option value="">{{ __('common.select') }}…</option>
                        @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id', $expense->category_id ?? null) == $cat->id)>
                            {{ $cat->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3 mb-3">
                    {{-- Amount --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ __('expenses.amount') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="number" name="amount" step="0.01" min="0.01"
                                   class="form-control @error('amount') is-invalid @enderror"
                                   value="{{ old('amount', $expense->amount ?? '') }}" required>
                        </div>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Date --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ __('expenses.date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="expense_date"
                               class="form-control @error('expense_date') is-invalid @enderror"
                               value="{{ old('expense_date', isset($expense) ? $expense->expense_date->format('Y-m-d') : today()->format('Y-m-d')) }}"
                               required>
                        @error('expense_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Payment Method --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('expenses.payment_method') }} <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                        <option value="cash" @selected(old('payment_method', $expense->payment_method ?? 'cash') === 'cash')>
                            <i class="bi bi-cash-stack"></i> Cash
                        </option>
                        <option value="bank" @selected(old('payment_method', $expense->payment_method ?? '') === 'bank')>Bank</option>
                        <option value="qr" @selected(old('payment_method', $expense->payment_method ?? '') === 'qr')>QR / UPI</option>
                    </select>
                    @error('payment_method')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Reference --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('expenses.reference') }}</label>
                    <input type="text" name="reference_number"
                           class="form-control @error('reference_number') is-invalid @enderror"
                           value="{{ old('reference_number', $expense->reference_number ?? '') }}"
                           placeholder="Optional voucher/receipt number">
                    @error('reference_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Description --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold">{{ __('expenses.description') }}</label>
                    <textarea name="description" rows="3"
                              class="form-control @error('description') is-invalid @enderror"
                              placeholder="Brief description of the expense…">{{ old('description', $expense->description ?? '') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>
                        {{ isset($expense) ? __('common.save_changes') : __('expenses.new_expense') }}
                    </button>
                    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

