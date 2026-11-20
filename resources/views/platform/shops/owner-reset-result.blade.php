@extends('layouts.app')

@section('title', __('platform.owner_reset_result_title'))

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h1 class="h4 mb-3">{{ __('platform.owner_reset_result_title') }}</h1>
                    <p class="mb-3"><strong>{{ __('platform.owner') }}:</strong> {{ $shop->ownerUser?->user?->email ?? '-' }}</p>

                    @if ($result)
                        <div class="alert alert-info">
                            {{ __('platform.owner_reset_shown_once') }}
                        </div>

                        <div class="mb-3">
                            <label for="reset-link" class="form-label">{{ __('platform.owner_reset_link') }}</label>
                            <div class="input-group">
                                <input type="text" id="reset-link" class="form-control" value="{{ $result['url'] }}" readonly>
                                <button type="button" id="copy-reset-link" class="btn btn-outline-secondary" style="min-height:44px;">{{ __('platform.copy') }}</button>
                            </div>
                        </div>

                        <p class="text-muted small mb-3">
                            {{ __('platform.owner_reset_expires_at', ['time' => \Carbon\Carbon::parse($result['expires_at'])->timezone('Asia/Kathmandu')->format('Y-m-d H:i')]) }}
                        </p>
                    @else
                        <div class="alert alert-secondary">
                            {{ __('platform.owner_reset_no_link') }}
                        </div>
                    @endif

                    <a href="{{ route('platform.shops.show', $shop) }}" class="btn btn-outline-secondary" style="min-height:44px;">{{ __('platform.back_to_shops') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>

@if ($result)
<script>
document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('copy-reset-link');
    if (!button) { return; }
    button.addEventListener('click', function () {
        var input = document.getElementById('reset-link');
        if (!input) { return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value);
        } else {
            input.select();
            document.execCommand('copy');
        }
    });
});
</script>
@endif
@endsection