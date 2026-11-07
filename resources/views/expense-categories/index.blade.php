@extends('layouts.app')

@section('title', __('expenses.categories'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1">{{ __('expenses.categories') }}</h1>
        </div>
        <div class="action-row d-flex gap-2">
            @canRoute('expense-categories.create')
            <a href="{{ route('expense-categories.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> {{ __('expenses.new_category') }}
            </a>
            @endCanRoute
            @canRoute('expenses.index')
            <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> {{ __('expenses.expenses') }}
            </a>
            @endCanRoute
        </div>
    </div>

    @foreach(['success','error'] as $msg)
    @if(session($msg))
    <div class="alert alert-{{ $msg === 'success' ? 'success' : 'danger' }} alert-dismissible fade show" role="alert">
        {{ session($msg) }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @endforeach

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('expenses.category') }}</th>
                        <th class="text-center">{{ __('expenses.expenses') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $cat)
                    <tr>
                        <td class="fw-semibold">{{ $cat->name }}</td>
                        <td class="text-center">
                            <span class="badge bg-secondary">{{ $cat->expenses_count }}</span>
                        </td>
                        <td class="text-end">
                            @canRoute('expense-categories.edit', $cat)
                            <a href="{{ route('expense-categories.edit', $cat) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endCanRoute
                            @canRoute('expense-categories.destroy', $cat)
                            <form action="{{ route('expense-categories.destroy', $cat) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('{{ __('common.confirm_delete') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endCanRoute
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">
                            <i class="bi bi-tags fs-2 d-block mb-2"></i>
                            {{ __('ui.no_expense_categories') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($categories->hasPages())
        <div class="card-footer bg-white border-0">{{ $categories->links() }}</div>
        @endif
    </div>
</div>
@endsection

