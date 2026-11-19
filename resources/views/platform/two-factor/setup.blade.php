@extends('layouts.app')

@section('title', __('platform.two_factor_setup_title'))

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <h1 class="h4 mb-3">{{ __('platform.two_factor_setup_title') }}</h1>
            <p class="text-muted">{{ __('platform.two_factor_setup_intro') }}</p>

            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('platform.two_factor_scan_qr') }}</h2>
                    <div class="text-center mb-3">
                        <img src="data:image/svg+xml;base64,{{ base64_encode($qrSvg) }}" alt="{{ __('platform.two_factor_qr_alt') }}" class="img-fluid" style="max-width:220px;">
                    </div>
                    <p class="mb-1 fw-semibold">{{ __('platform.two_factor_manual_key') }}</p>
                    <code class="d-block p-2 bg-light border rounded user-select-all" style="min-height:44px; word-break:break-all;">{{ $secret }}</code>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('platform.2fa.setup.confirm') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="code" class="form-label">{{ __('platform.two_factor_code') }}</label>
                            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="form-control @error('code') is-invalid @enderror" style="min-height:44px;" required autofocus>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100" style="min-height:44px;">{{ __('platform.two_factor_confirm') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
