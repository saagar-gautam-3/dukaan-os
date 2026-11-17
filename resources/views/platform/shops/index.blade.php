@extends('layouts.app')

@section('title', __('platform.shops'))

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ __('platform.shops') }}</h1>
    </div>

    <form method="GET" action="{{ route('platform.shops.index') }}" class="row g-2 mb-3">
        <div class="col-12 col-md-6">
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="{{ __('platform.search_placeholder') }}" aria-label="{{ __('platform.search') }}">
        </div>
        <div class="col-6 col-md-3">
            <select name="status" class="form-select" aria-label="{{ __('platform.filter_status') }}">
                <option value="all" @selected($status === 'all')>{{ __('platform.all') }}</option>
                <option value="active" @selected($status === 'active')>{{ __('platform.active') }}</option>
                <option value="suspended" @selected($status === 'suspended')>{{ __('platform.suspended') }}</option>
            </select>
        </div>
        <div class="col-6 col-md-3 d-grid">
            <button type="submit" class="btn btn-primary">{{ __('platform.search') }}</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>{{ __('platform.name') }}</th>
                    <th>{{ __('platform.owner') }}</th>
                    <th>{{ __('platform.created') }}</th>
                    <th>{{ __('platform.status') }}</th>
                    <th>{{ __('platform.users') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shops as $shop)
                    <tr>
                        <td><a href="{{ route('platform.shops.show', $shop) }}">{{ $shop->name }}</a></td>
                        <td>{{ $shop->ownerUser?->user?->email ?? '-' }}</td>
                        <td>{{ $shop->created_at }}</td>
                        <td>
                            @if ($shop->is_active)
                                <span class="badge text-bg-success">{{ __('platform.active') }}</span>
                            @else
                                <span class="badge text-bg-danger">{{ __('platform.suspended') }}</span>
                                @if ($shop->suspended_at)
                                    <div class="small text-muted">{{ $shop->suspended_at }}</div>
                                @endif
                                @if ($shop->suspended_reason)
                                    <div class="small text-muted">{{ $shop->suspended_reason }}</div>
                                @endif
                            @endif
                        </td>
                        <td>{{ $shop->users_count }}</td>
                        <td class="text-end">
                            @if ($shop->is_active)
                                <a href="{{ route('platform.shops.suspend', $shop) }}" class="btn btn-sm btn-outline-danger" style="min-height:44px;min-width:44px;">{{ __('platform.suspend') }}</a>
                            @else
                                <form method="POST" action="{{ route('platform.shops.reactivate', $shop) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success" style="min-height:44px;min-width:44px;">{{ __('platform.reactivate') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">{{ __('platform.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $shops->links() }}
</div>
@endsection