@extends('layouts.app')

@section('title', isset($category) ? __('expenses.edit_category') : __('expenses.new_category'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('expense-categories.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 fw-bold mb-0">{{ isset($category) ? __('expenses.edit_category') : __('expenses.new_category') }}</h1>
    </div>

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card border-0 shadow-sm" style="max-width:480px">
        <div class="card-body p-4">
            <form action="{{ isset($category) ? route('expense-categories.update', $category) : route('expense-categories.store') }}" method="POST">
                @csrf
                @isset($category)
                    @method('PUT')
                @endisset

                <div class="mb-4">
                    <label class="form-label fw-semibold">{{ __('expenses.category') }} {{ __('ui.name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name"
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $category->name ?? '') }}"
                           required autofocus placeholder="{{ __('expenses.category_placeholder') }}">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="action-row d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>
                        {{ isset($category) ? __('common.save_changes') : __('expenses.new_category') }}
                    </button>
                    <a href="{{ route('expense-categories.index') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

