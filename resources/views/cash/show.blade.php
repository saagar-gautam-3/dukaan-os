@extends('layouts.app')

@section('title', __('cash.session_detail'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('cash.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 fw-bold mb-0">{{ __('cash.session_detail') }}</h1>
        @if($cashSession->isOpen())
            <span class="badge bg-success fs-6"><i class="bi bi-unlock me-1"></i>{{ __('cash.status_open') }}</span>
        @else
            <span class="badge bg-secondary fs-6"><i class="bi bi-lock me-1"></i>{{ __('cash.status_closed') }}</span>
        @endif
    </div>

    @foreach(['success','error'] as $msg)
    @if(session($msg))
    <div class="alert alert-{{ $msg === 'success' ? 'success' : 'danger' }} alert-dismissible fade show">
        {{ session($msg) }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @endforeach

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="text-muted small">{{ __('cash.opening_cash') }}</div>
                <div class="h4 fw-bold text-primary mt-1">@npr($detail['opening_cash'])</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="text-muted small">{{ __('cash.cash_in') }}</div>
                <div class="h4 fw-bold text-success mt-1">@npr($detail['cash_in'])</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="text-muted small">{{ __('cash.cash_out') }}</div>
                <div class="h4 fw-bold text-danger mt-1">@npr($detail['cash_out'])</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="text-muted small">{{ __('cash.expected_cash') }}</div>
                <div class="h4 fw-bold text-dark mt-1">@npr($detail['expected_cash'])</div>
            </div>
        </div>
    </div>

    @if(!$cashSession->isOpen())
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="text-muted small">{{ __('cash.closing_cash') }}</div>
                <div class="h4 fw-bold mt-1">@npr($detail['closing_cash'])</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            @php $diff = (string) $detail['difference']; @endphp
            <div class="card border-0 shadow-sm text-center p-3 {{ \App\Support\Money::compare($diff, '0', 2) < 0 ? 'border-danger' : 'border-success' }}">
                <div class="text-muted small">{{ __('cash.difference') }}</div>
                <div class="h4 fw-bold {{ \App\Support\Money::compare($diff, '0', 2) < 0 ? 'text-danger' : 'text-success' }} mt-1">
                    @npr(\App\Support\Money::compare($diff, '0', 2) < 0 ? \App\Support\Money::subtract('0', $diff, 2) : $diff)
                    <small class="fs-6">({{ \App\Support\Money::compare($diff, '0', 2) < 0 ? __('cash.shortage') : __('cash.overage') }})</small>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Movements Table --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">{{ __('cash.movements') }}</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('cash.time_col') }}</th>
                        <th>{{ __('cash.type_col') }}</th>
                        <th>{{ __('cash.notes_col') }}</th>
                        <th class="text-end text-success">{{ __('cash.cash_in') }}</th>
                        <th class="text-end text-danger">{{ __('cash.cash_out') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($detail['movements'] as $m)
                    @php
                        $type = $m->type instanceof \App\Enums\CashMovementType ? $m->type : \App\Enums\CashMovementType::from($m->type);
                        $isIn = $type->isInflow();
                    @endphp
                    <tr>
                        <td class="text-muted small">{{ $m->created_at->format('H:i') }}</td>
                        <td>
                            <span class="badge bg-{{ $isIn ? 'success' : 'danger' }} bg-opacity-10 text-{{ $isIn ? 'success' : 'danger' }}">
                                {{ ucfirst(str_replace('_', ' ', $type->value)) }}
                            </span>
                        </td>
                        <td>{{ $m->notes ?? '—' }}</td>
                        <td class="text-end text-success">{{ $isIn && $type !== \App\Enums\CashMovementType::Opening ? format_npr($m->amount) : '—' }}</td>
                        <td class="text-end text-danger">{{ !$isIn ? format_npr($m->amount) : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Close Session --}}
    @if($cashSession->isOpen())
    @canRoute('cash.close', $cashSession)
    <div class="card border-0 shadow-sm border-top border-warning" style="max-width:480px">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">{{ __('cash.close_session') }}</h5>
            <form action="{{ route('cash.close', $cashSession) }}" method="POST">
                @csrf @method('POST')
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('cash.closing_cash') }} <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">{{ __('ui.currency_symbol') }}</span>
                        <input type="number" name="closing_cash" step="0.01" min="0"
                               class="form-control" value="{{ $detail['expected_cash'] }}" required>
                    </div>
                    <div class="form-text">{{ __('cash.expected_hint') }} @npr($detail['expected_cash'])</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('cash.notes') }}</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-warning"
                        onclick="return confirm(@js(__('cash.confirm_close_session')))">
                    <i class="bi bi-lock me-1"></i> {{ __('cash.close_session') }}
                </button>
            </form>
        </div>
    </div>
    @endCanRoute
    @endif
</div>
@endsection

