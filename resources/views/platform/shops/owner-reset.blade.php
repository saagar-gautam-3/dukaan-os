@extends('layouts.app')

@section('title', __('platform.owner_reset_title'))

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h1 class="h4 mb-3">{{ __('platform.owner_reset_title') }}</h1>
                    <p class="mb-1"><strong>{{ __('platform.name') }}:</strong> {{ $shop->name }}</p>
                    <p class="mb-3"><strong>{{ __('platform.owner') }}:</strong> {{ $shop->ownerUser?->user?->email ?? '-' }}</p>

                    <div class="alert alert-warning">
                        {{ __('platform.owner_reset_warning') }}
                    </div>

                    <p class="text-muted small mb-3">
                        {{ __('platform.owner_reset_expiry_note', ['minutes' => $expiryMinutes]) }}
                    </p>

                    <form method="POST" action="{{ route('platform.shops.owner-reset.store', $shop) }}">
                        @csrf
                        <div class="mb-3">
                            <label for="reason" class="form-label">{{ __('platform.reason') }}</label>
                            <textarea name="reason" id="reason" rows="4" class="form-control @error('reason') is-invalid @enderror" placeholder="{{ __('platform.reason_placeholder') }}" required>{{ old('reason') }}</textarea>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="current_password" class="form-label">{{ __('platform.current_password') }}</label>
                            <input type="password" name="current_password" id="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @if ($mailConfigured)
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="email_owner" id="email_owner" value="1" @checked(old('email_owner'))>
                                <label class="form-check-label" for="email_owner">{{ __('platform.email_owner') }}</label>
                            </div>
                        @endif

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning" style="min-height:44px;">{{ __('platform.owner_reset_submit') }}</button>
                            <a href="{{ route('platform.shops.show', $shop) }}" class="btn btn-outline-secondary" style="min-height:44px;">{{ __('platform.cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection