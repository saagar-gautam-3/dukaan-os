@extends('layouts.auth')

@section('title', __('shops.create_title').' — '.config('app.name'))

@section('content')
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-4 p-md-5">
            <h1 class="h4 fw-bold text-center mb-4">{{ __('shops.create_title') }}</h1>

            <form method="POST" action="{{ route('shops.store') }}">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">{{ __('shops.name') }}</label>
                    <input id="name" name="name" type="text" maxlength="255" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label for="business_type" class="form-label">{{ __('shops.business_type') }}</label>
                    <select id="business_type" name="business_type" class="form-select @error('business_type') is-invalid @enderror" required>
                        @foreach (['kirana', 'clothing', 'electronics', 'pharmacy', 'hardware', 'general'] as $type)
                            <option value="{{ $type }}" @selected(old('business_type', 'kirana') === $type)>{{ __('shops.types.'.$type) }}</option>
                        @endforeach
                    </select>
                    @error('business_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">{{ __('shops.register_new') }}</button>
                    <a href="{{ route('shops.picker') }}" class="btn btn-link">{{ __('common.back') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
