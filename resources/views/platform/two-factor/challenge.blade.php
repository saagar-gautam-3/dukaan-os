@extends('layouts.app')

@section('title', __('platform.two_factor_challenge_title'))

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">
            <h1 class="h4 mb-3">{{ __('platform.two_factor_challenge_title') }}</h1>
            <p class="text-muted">{{ __('platform.two_factor_challenge_intro') }}</p>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('platform.2fa.challenge.verify') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="code" class="form-label">{{ __('platform.two_factor_code_or_recovery') }}</label>
                            <input id="code" name="code" type="text" autocomplete="one-time-code" class="form-control @error('code') is-invalid @enderror" style="min-height:44px;" required autofocus>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100" style="min-height:44px;">{{ __('platform.two_factor_verify') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
