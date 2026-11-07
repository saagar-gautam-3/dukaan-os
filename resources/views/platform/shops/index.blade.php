@extends('layouts.app')

@section('title', __('platform.shops'))

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ __('platform.shops') }}</h1>
        @canRoute('shops.create')
        <a class="btn btn-primary" href="{{ route('shops.create') }}">{{ __('shops.register_new') }}</a>
        @endCanRoute
    </div>
    <div class="table-responsive">
        <table class="table">
        <thead>
            <tr>
                <th>{{ __('platform.name') }}</th>
                <th>{{ __('platform.owner') }}</th>
                <th>{{ __('platform.created') }}</th>
                <th>{{ __('platform.active') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($shops as $shop)
                <tr>
                    <td>{{ $shop->name }}</td>
                    <td>{{ $shop->users->first()?->email ?? '-' }}</td>
                    <td>{{ $shop->created_at }}</td>
                    <td>{{ $shop->is_active ? __('common.yes') : __('common.no') }}</td>
                    <td>
                        @canRoute('platform.shops.toggle')
                        <form method="POST" action="{{ route('platform.shops.toggle', $shop) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('platform.toggle') }}</button>
                        </form>
                        @endCanRoute
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    {{ $shops->links() }}
</div>
@endsection
