@extends('layouts.app')

@section('title', __('cash.sessions_history'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-cash-coin me-2"></i>{{ __('cash.sessions_history') }}</h1>
        </div>
        @can('create', \App\Models\CashSession::class)
        @if(!$currentSession)
        <a href="#openModal" data-bs-toggle="modal" data-bs-target="#openModal" class="btn btn-success">
            <i class="bi bi-unlock me-1"></i> {{ __('cash.open_session') }}
        </a>
        @else
        <a href="{{ route('cash.show', $currentSession) }}" class="btn btn-warning">
            <i class="bi bi-cash-stack me-1"></i> {{ __('cash.session_detail') }}
        </a>
        @endif
        @endcan
    </div>

    @foreach(['success','error'] as $msg)
    @if(session($msg))
    <div class="alert alert-{{ $msg === 'success' ? 'success' : 'danger' }} alert-dismissible fade show">
        {{ session($msg) }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @endforeach

    @if($currentSession)
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            <strong>{{ __('cash.status_open') }}:</strong>
            Session opened at {{ $currentSession->opened_at->format('d M Y, h:i A') }}
            | Opening: Rs {{ number_format($currentSession->opening_cash, 2) }}
            | Expected now: Rs {{ number_format($currentSession->computeExpectedCash(), 2) }}
        </div>
    </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('cash.opened_at') }}</th>
                        <th>{{ __('cash.opened_by') }}</th>
                        <th class="text-end">{{ __('cash.opening_cash') }}</th>
                        <th class="text-end">{{ __('cash.expected_cash') }}</th>
                        <th class="text-end">{{ __('cash.closing_cash') }}</th>
                        <th class="text-end">{{ __('cash.difference') }}</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                    <tr>
                        <td>{{ $session->opened_at->format('d M Y, H:i') }}</td>
                        <td>{{ $session->openedBy?->name ?? '—' }}</td>
                        <td class="text-end">Rs {{ number_format($session->opening_cash, 2) }}</td>
                        <td class="text-end">Rs {{ $session->expected_cash ? number_format($session->expected_cash, 2) : '—' }}</td>
                        <td class="text-end">{{ $session->closing_cash ? 'Rs '.number_format($session->closing_cash, 2) : '—' }}</td>
                        <td class="text-end {{ $session->difference !== null ? ($session->difference < 0 ? 'text-danger' : 'text-success') : '' }}">
                            {{ $session->difference !== null ? 'Rs '.number_format(abs($session->difference), 2).($session->difference < 0 ? ' (−)' : ' (+)') : '—' }}
                        </td>
                        <td>
                            @if($session->isOpen())
                            <span class="badge bg-success"><i class="bi bi-unlock me-1"></i>{{ __('cash.status_open') }}</span>
                            @else
                            <span class="badge bg-secondary"><i class="bi bi-lock me-1"></i>{{ __('cash.status_closed') }}</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('cash.show', $session) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-cash-coin fs-2 d-block mb-2"></i>
                            No cash sessions recorded yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sessions->hasPages())
        <div class="card-footer bg-white border-0">{{ $sessions->links() }}</div>
        @endif
    </div>
</div>

{{-- Open Session Modal --}}
<div class="modal fade" id="openModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('cash.open') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-unlock me-1"></i> {{ __('cash.open_session') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('cash.opening_cash') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="number" name="opening_cash" step="0.01" min="0"
                                   class="form-control" value="0.00" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional note…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-unlock me-1"></i> {{ __('cash.open_session') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

