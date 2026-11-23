@extends('layouts.app')

@section('title', __('suppliers.record_payment').' — '.config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-cash-coin me-2"></i>{{ __('suppliers.record_payment') }}
            </h2>
            <p class="text-muted mb-0 small">{{ $supplier->name }}</p>
        </div>
        <a href="{{ route('suppliers.dues') }}" class="btn btn-outline-secondary btn-sm" style="min-height:44px;">
            <i class="bi bi-arrow-left me-1"></i>{{ __('suppliers.dues_title') }}
        </a>
    </div>

    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <div class="text-muted small">{{ __('suppliers.outstanding_balance') }}</div>
                <div class="h4 fw-bold mb-0 text-primary">@npr($balance)</div>
            </div>
            <a href="{{ route('suppliers.index') }}" class="btn btn-outline-primary btn-sm" style="min-height:44px;">
                <i class="bi bi-person-lines-fill me-1"></i>{{ __('suppliers.title') }}
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            @include('suppliers.payments.form')
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <h5 class="p-3 mb-0 border-bottom fw-bold">{{ __('suppliers.ledger') }}</h5>
            @if($entries->isEmpty())
                <div class="text-center py-4 text-muted">{{ __('suppliers.no_ledger_entries') }}</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('ui.date') }}</th>
                                <th>{{ __('suppliers.type') }}</th>
                                <th class="text-end">{{ __('suppliers.debit') }}</th>
                                <th class="text-end">{{ __('suppliers.credit') }}</th>
                                <th class="text-end">{{ __('suppliers.running_balance') }}</th>
                                <th>{{ __('suppliers.notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entries as $entry)
                            <tr>
                                <td class="text-nowrap">{{ $entry->entry_date ?? '—' }}</td>
                                <td>{{ __('suppliers.entry_type.'.$entry->entry_type->value) }}</td>
                                <td class="text-end">@npr($entry->debit)</td>
                                <td class="text-end">@npr($entry->credit)</td>
                                <td class="text-end fw-bold">@npr($entry->running_balance)</td>
                                <td class="small text-muted">{{ $entry->notes }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $entries->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
