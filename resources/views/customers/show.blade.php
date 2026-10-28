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
            @can('collect', $customer)
            <a href="{{ route('udhar.collect', $customer) }}" class="btn btn-success">
                <i class="bi bi-cash-stack me-1"></i> {{ __('udhar.collect_payment') }}
            </a>
            @endcan
            @can('remind', $customer)
            <form method="POST" action="{{ route('udhar.remind', $customer) }}" class="d-inline" onsubmit="return confirm('{{ __('udhar.send_reminder') }}?');">
                @csrf
                <button type="submit" class="btn btn-outline-warning">
                    <i class="bi bi-bell me-1"></i> {{ __('udhar.send_reminder') }}
                </button>
            </form>
            @endcan
            @can('update', $customer)
            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i> {{ __('common.edit') }}
            </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row g-4">
        <!-- Left Sidebar: Details & Balance -->
        <div class="col-lg-4">
            <!-- Balance & Credit Limit Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="mb-0 fw-bold">{{ __('udhar.balance') }}</h5>
                </div>
                <div class="card-body p-3">
                    <div class="p-3 {{ bccomp($balance, '0', 2) > 0 ? 'bg-danger-subtle' : 'bg-success-subtle' }} rounded text-center mb-3">
                        <small class="text-muted text-uppercase fw-semibold d-block mb-1">{{ __('udhar.total_outstanding') }}</small>
                        <div class="h3 fw-bold {{ bccomp($balance, '0', 2) > 0 ? 'text-danger' : 'text-success' }} mb-0">
                            NPR @npr($balance)
                        </div>
                    </div>

                    @if((float) $customer->credit_limit > 0)
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">{{ __('udhar.credit_limit') }}:</small>
                            <span class="fw-bold">NPR @npr($customer->credit_limit)</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-muted">{{ __('udhar.credit_headroom') }}:</small>
                            <span class="fw-semibold {{ bccomp($headroom, '0', 2) < 0 ? 'text-danger' : 'text-success' }}">
                                NPR @npr($headroom)
                            </span>
                        </div>
                        @if(bccomp($headroom, '0', 2) <= 0)
                            <div class="badge bg-danger w-100 py-2">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ __('udhar.credit_limit_exceeded_notice') }}
                            </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            <!-- Contact Details Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="mb-0 fw-bold">{{ __('udhar.customer_details') }}</h5>
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
                    <div class="mb-0">
                        <small class="text-muted d-block">PAN / VAT</small>
                        <span>{{ $customer->pan_no ?: 'Not provided' }}</span>
                    </div>
                </div>
            </div>

            <!-- Reminder Logs Card -->
            @if(isset($reminderLogs) && $reminderLogs->isNotEmpty())
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-bell text-warning me-1"></i> Reminder History
                    </h6>
                </div>
                <div class="list-group list-group-flush small">
                    @foreach($reminderLogs as $log)
                    <div class="list-group-item p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-secondary">{{ strtoupper($log->channel) }}</span>
                            <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                        </div>
                        <div class="text-muted mb-1">
                            To: <span class="fw-semibold">{{ $log->phone_masked }}</span>
                        </div>
                        <div class="fst-italic text-secondary">
                            "{{ $log->message_preview }}"
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <!-- Main Content: Tabs / Sections -->
        <div class="col-lg-8">
            <!-- Open Invoices -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-file-earmark-text text-primary me-2"></i>{{ __('udhar.open_invoices') }}
                    </h5>
                    @if(isset($openSales) && $openSales->isNotEmpty())
                    <span class="badge bg-danger">{{ $openSales->count() }} Unpaid / Partial</span>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('udhar.invoice_number') }}</th>
                                <th>{{ __('udhar.due_date') }}</th>
                                <th class="text-end">{{ __('udhar.original_total') }}</th>
                                <th class="text-end">{{ __('udhar.paid_total') }}</th>
                                <th class="text-end">{{ __('udhar.remaining_due') }}</th>
                                <th class="text-end">{{ __('udhar.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($openSales as $sale)
                            @php $due = $sale->dueAmount(); @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('sales.show', $sale) }}" class="fw-bold text-decoration-none">
                                        {{ $sale->invoice_no ?? ('Draft #' . $sale->id) }}
                                    </a>
                                </td>
                                <td>
                                    @if($sale->due_date)
                                        <span class="{{ $sale->due_date < now()->toDateString() ? 'text-danger fw-bold' : '' }}">
                                            {{ $sale->due_date }}
                                        </span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td class="text-end">NPR @npr($sale->grand_total)</td>
                                <td class="text-end text-success">NPR @npr($sale->paid_total)</td>
                                <td class="text-end fw-bold text-danger">NPR @npr($due)</td>
                                <td class="text-end">
                                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-outline-secondary" title="View Bill">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="bi bi-check-circle text-success me-1"></i>{{ __('udhar.no_open_invoices') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Ledger History (Append-only source of truth) -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-journal-bookmark text-success me-2"></i>{{ __('udhar.ledger_history') }}
                        </h5>
                        <small class="text-muted">Immutable financial ledger entries</small>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('udhar.date') }}</th>
                                <th>{{ __('udhar.entry_type') }}</th>
                                <th>{{ __('udhar.reference') }}</th>
                                <th class="text-end text-danger">{{ __('udhar.debit') }}</th>
                                <th class="text-end text-success">{{ __('udhar.credit') }}</th>
                                <th>{{ __('udhar.notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ledgerEntries as $entry)
                            <tr>
                                <td>
                                    <small>{{ $entry->created_at->format('Y-m-d H:i') }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        {{ $entry->entry_type->label() }}
                                    </span>
                                </td>
                                <td>
                                    @if($entry->reference_type && $entry->reference_id)
                                        <span class="small text-muted">{{ $entry->reference_type }} #{{ $entry->reference_id }}</span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if(bccomp((string) $entry->debit, '0', 2) > 0)
                                        <span class="text-danger fw-bold">NPR @npr($entry->debit)</span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if(bccomp((string) $entry->credit, '0', 2) > 0)
                                        <span class="text-success fw-bold">NPR @npr($entry->credit)</span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">{{ $entry->notes ?: '-' }}</small>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    {{ __('udhar.no_ledger_entries') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(isset($ledgerEntries) && $ledgerEntries->hasPages())
                <div class="card-footer bg-white border-top p-3">
                    {{ $ledgerEntries->links() }}
                </div>
                @endif
            </div>

            <!-- Recent Sales History -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Recent Bills</h5>
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
                                <td class="text-end fw-bold">NPR @npr($sale->grand_total)</td>
                                <td class="text-end text-success">NPR @npr($sale->paid_total)</td>
                                <td class="text-end">
                                    @php $due = $sale->dueAmount(); @endphp
                                    @if(bccomp((string) $due, '0', 2) > 0)
                                        <span class="text-danger fw-bold">NPR @npr($due)</span>
                                    @else
                                        <span class="text-muted">NPR 0.00</span>
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
