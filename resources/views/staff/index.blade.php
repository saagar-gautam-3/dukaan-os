@extends('layouts.app')

@section('title', __('staff.title') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-people me-2"></i>{{ __('staff.title') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('staff.subtitle') }}</p>
        </div>
        <a href="{{ route('staff.create') }}" class="btn btn-primary mt-3 mt-md-0">
            <i class="bi bi-person-plus me-1"></i>{{ __('staff.add_staff') }}
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            @if ($staffMembers->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                    <p class="mb-0">{{ __('staff.no_staff') }}</p>
                </div>
            @else
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">{{ __('staff.name') }}</th>
                                <th>{{ __('staff.email') }}</th>
                                <th>{{ __('staff.phone') }}</th>
                                <th>{{ __('staff.role') }}</th>
                                <th>{{ __('staff.joined') }}</th>
                                <th class="text-end pe-3">{{ __('staff.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($staffMembers as $member)
                                <tr>
                                    <td class="ps-3 fw-semibold">{{ $member->user?->name }}</td>
                                    <td>{{ $member->user?->email }}</td>
                                    <td>{{ $member->user?->phone }}</td>
                                    <td>{{ $member->role?->name ?? __('staff.unknown_role') }}</td>
                                    <td>{{ $member->created_at?->format('Y-m-d') }}</td>
                                    <td class="text-end pe-3">
                                        @can('update', $member)
                                            <a href="{{ route('staff.edit', $member) }}" class="btn btn-outline-primary" aria-label="{{ __('staff.change_role_for', ['name' => $member->user?->name]) }}">
                                                <i class="bi bi-person-gear"></i>
                                            </a>
                                        @endcan
                                        @can('delete', $member)
                                            <form method="POST" action="{{ route('staff.destroy', $member) }}" class="d-inline" onsubmit="return confirm(@js(__('staff.confirm_remove', ['name' => $member->user?->name])))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" aria-label="{{ __('staff.remove_staff_member', ['name' => $member->user?->name]) }}">
                                                    <i class="bi bi-person-x"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-md-none">
                    @foreach ($staffMembers as $member)
                        <article class="p-3 border-bottom">
                            <div class="d-flex justify-content-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="h6 fw-bold mb-1 text-break">{{ $member->user?->name }}</h3>
                                    <div class="small text-muted text-break">{{ $member->user?->email }}</div>
                                    <div class="small text-muted">{{ $member->user?->phone }}</div>
                                </div>
                                <span class="badge text-bg-light align-self-start">{{ $member->role?->name ?? __('staff.unknown_role') }}</span>
                            </div>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-2 border-top">
                                <small class="text-muted">{{ __('staff.joined') }}: {{ $member->created_at?->format('Y-m-d') }}</small>
                                <div class="d-flex gap-2">
                                    @can('update', $member)
                                        <a href="{{ route('staff.edit', $member) }}" class="btn btn-outline-primary" aria-label="{{ __('staff.change_role_for', ['name' => $member->user?->name]) }}">
                                            <i class="bi bi-person-gear"></i>
                                        </a>
                                    @endcan
                                    @can('delete', $member)
                                        <form method="POST" action="{{ route('staff.destroy', $member) }}" onsubmit="return confirm(@js(__('staff.confirm_remove', ['name' => $member->user?->name])))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" aria-label="{{ __('staff.remove_staff_member', ['name' => $member->user?->name]) }}">
                                                <i class="bi bi-person-x"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small">{{ __('staff.showing_members', ['first' => $staffMembers->firstItem() ?? 0, 'last' => $staffMembers->lastItem() ?? 0, 'total' => $staffMembers->total()]) }}</span>
                    {{ $staffMembers->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
