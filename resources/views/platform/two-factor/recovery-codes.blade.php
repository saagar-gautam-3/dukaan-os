@extends('layouts.app')

@section('title', __('platform.two_factor_recovery_codes_title'))

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <h1 class="h4 mb-3">{{ __('platform.two_factor_recovery_codes_title') }}</h1>

            @if (!empty($recoveryCodes))
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>{{ __('platform.two_factor_recovery_codes_warning') }}
                </div>
                <p class="text-muted">{{ __('platform.two_factor_recovery_codes_once_intro') }}</p>
                <div class="card mb-3">
                    <div class="card-body">
                        <ul class="list-unstyled mb-0 row g-2">
                            @foreach ($recoveryCodes as $code)
                                <li class="col-6"><code class="d-block p-2 bg-light border rounded text-center" style="min-height:44px;">{{ $code }}</code></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <a href="{{ route('platform.shops.index') }}" class="btn btn-primary w-100" style="min-height:44px;">{{ __('platform.two_factor_continue') }}</a>
            @else
                <p class="text-muted">{{ __('platform.two_factor_regenerate_intro') }}</p>
                <div class="mb-3">
                    <span class="badge text-bg-secondary">{{ __('platform.two_factor_remaining_codes', ['count' => $remaining]) }}</span>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('platform.2fa.recovery-codes.regenerate') }}" novalidate>
                            @csrf
                            <div class="mb-3">
                                <label for="password" class="form-label">{{ __('platform.two_factor_current_password') }}</label>
                                <input id="password" name="password" type="password" autocomplete="current-password" class="form-control @error('password') is-invalid @enderror" style="min-height:44px;" required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="code" class="form-label">{{ __('platform.two_factor_current_code') }}</label>
                                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="form-control @error('code') is-invalid @enderror" style="min-height:44px;" required>
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-primary w-100" style="min-height:44px;">{{ __('platform.two_factor_regenerate') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
