@extends('layouts.app')

@section('title', __('expenses.expenses'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1">{{ __('expenses.expenses') }}</h1>
            <p class="text-muted small mb-0">{{ __('expenses.total_expenses') }}: Rs {{ number_format($expenses->sum('amount'), 2) }}</p>
        </div>
        <div class="d-flex gap-2">
            @can('create', \App\Models\Expense::class)
            <a href="{{ route('expenses.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> {{ __('expenses.new_expense') }}
            </a>
            @endcan
            @can('create', \App\Models\ExpenseCategory::class)
            <a href="{{ route('expense-categories.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-tags me-1"></i> {{ __('expenses.categories') }}
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
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('expenses.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">{{ __('expenses.filter_by_category') }}</label>
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">{{ __('common.all') }}</option>
                        @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">{{ __('expenses.date') }} {{ __('reports.from') }}</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">{{ __('expenses.date') }} {{ __('reports.to') }}</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-dark btn-sm"><i class="bi bi-funnel me-1"></i> Filter</button>
                    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('expenses.date') }}</th>
                        <th>{{ __('expenses.category') }}</th>
                        <th>{{ __('expenses.description') }}</th>
                        <th>{{ __('expenses.payment_method') }}</th>
                        <th class="text-end">{{ __('expenses.amount') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $expense)
                    <tr>
                        <td>{{ $expense->expense_date->format('d M Y') }}</td>
                        <td><span class="badge bg-secondary">{{ $expense->category?->name ?? '—' }}</span></td>
                        <td class="text-truncate" style="max-width:200px">{{ $expense->description ?? '—' }}</td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-{{ $expense->payment_method === 'cash' ? 'cash-stack' : ($expense->payment_method === 'bank' ? 'bank' : 'qr-code') }} me-1"></i>
                                {{ ucfirst($expense->payment_method) }}
                            </span>
                        </td>
                        <td class="text-end fw-semibold">Rs {{ number_format($expense->amount, 2) }}</td>
                        <td class="text-end">
                            @can('update', $expense)
                            <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endcan
                            @can('delete', $expense)
                            <form action="{{ route('expenses.destroy', $expense) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('{{ __('common.confirm_delete') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-receipt fs-2 d-block mb-2"></i>
                            No expenses recorded yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($expenses->hasPages())
        <div class="card-footer bg-white border-0">
            {{ $expenses->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

