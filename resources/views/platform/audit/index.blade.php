@extends('layouts.app')

@section('title', __('platform.audit'))

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ __('platform.audit') }}</h1>
        <a href="{{ route('platform.shops.index') }}" class="btn btn-sm btn-outline-secondary" style="min-height:44px;">{{ __('platform.back_to_shops') }}</a>
    </div>

    <form method="GET" action="{{ route('platform.audit.index') }}" class="row g-2 mb-3">
        <div class="col-12 col-md-3">
            <select name="admin" class="form-select" aria-label="{{ __('platform.admin') }}">
                <option value="">{{ __('platform.all_admins') }}</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected((string) $adminId === (string) $admin->id)>{{ $admin->email }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <select name="shop" class="form-select" aria-label="{{ __('platform.shop') }}">
                <option value="">{{ __('platform.all_shops') }}</option>
                @foreach ($shops as $shopOption)
                    <option value="{{ $shopOption->id }}" @selected((string) $shopId === (string) $shopOption->id)>{{ $shopOption->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <select name="event" class="form-select" aria-label="{{ __('platform.event') }}">
                <option value="">{{ __('platform.all_events') }}</option>
                @foreach ($events as $eventName)
                    <option value="{{ $eventName }}" @selected($event === $eventName)>{{ $eventName }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <input type="date" name="from" value="{{ $from }}" class="form-control" aria-label="{{ __('platform.from') }}">
        </div>
        <div class="col-6 col-md-2">
            <input type="date" name="to" value="{{ $to }}" class="form-control" aria-label="{{ __('platform.to') }}">
        </div>
        <div class="col-12 d-grid d-md-block">
            <button type="submit" class="btn btn-primary" style="min-height:44px;">{{ __('platform.filter') }}</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>{{ __('platform.time') }}</th>
                    <th>{{ __('platform.admin') }}</th>
                    <th>{{ __('platform.event') }}</th>
                    <th>{{ __('platform.shop') }}</th>
                    <th>{{ __('platform.reason') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->timezone('Asia/Kathmandu') }}</td>
                        <td>{{ $log->user?->email ?? '-' }}</td>
                        <td>
                            <span class="d-block">{{ __('platform.events.'.str_replace('.', '_', $log->event)) }}</span>
                            <span class="text-muted small">{{ $log->event }}</span>
                        </td>
                        <td>{{ $log->auditable instanceof \App\Models\Shop ? $log->auditable->name : '-' }}</td>
                        <td>{{ $log->new_values['reason'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">{{ __('platform.empty_audit') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
@endsection