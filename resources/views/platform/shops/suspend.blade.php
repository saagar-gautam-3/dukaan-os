@extends('layouts.app')

@section('title', __('platform.suspend_title'))

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h1 class="h4 mb-3">{{ __('platform.suspend_title') }}</h1>
                    <p class="mb-1"><strong>{{ __('platform.name') }}:</strong> {{ $shop->name }}</p>
                    <p class="mb-3"><strong>{{ __('platform.owner') }}:</strong> {{ $shop->ownerUser?->user?->email ?? '-' }}</p>

                    <form method="POST" action="{{ route('platform.shops.suspend.store', $shop) }}">
                        @csrf
                        <div class="mb-3">
                            <label for="reason" class="form-label">{{ __('platform.reason') }}</label>
                            <textarea name="reason" id="reason" rows="4" class="form-control @error('reason') is-invalid @enderror" placeholder="{{ __('platform.reason_placeholder') }}" required>{{ old('reason') }}</textarea>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger" style="min-height:44px;">{{ __('platform.confirm_suspend') }}</button>
                            <a href="{{ route('platform.shops.index') }}" class="btn btn-outline-secondary" style="min-height:44px;">{{ __('platform.cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection