@extends('layouts.app')

@section('title', (isset($staffMember) ? __('staff.change_role_title') : __('staff.add_staff_title')) . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0" style="max-width: 800px;">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-{{ isset($staffMember) ? 'person-gear' : 'person-plus' }} me-2"></i>
                {{ isset($staffMember) ? __('staff.change_role_title') : __('staff.add_staff_title') }}
            </h2>
            <p class="text-muted mb-0 small">{{ isset($staffMember) ? $staffMember->user?->name : __('staff.subtitle') }}</p>
        </div>
        <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>{{ __('common.back') }}
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-4">
            <form method="POST" action="{{ isset($staffMember) ? route('staff.update', $staffMember) : route('staff.store') }}">
                @csrf
                @isset($staffMember)
                    @method('PUT')
                    <div class="mb-3">
                        <div class="fw-semibold">{{ $staffMember->user?->name }}</div>
                        <div class="small text-muted">{{ $staffMember->user?->email }}</div>
                    </div>
                @else
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="name" class="form-label fw-semibold">{{ __('staff.name') }}</label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255" autocomplete="name" class="form-control @error('name') is-invalid @enderror">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="email" class="form-label fw-semibold">{{ __('staff.email') }}</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" class="form-control @error('email') is-invalid @enderror">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="phone" class="form-label fw-semibold">{{ __('staff.phone') }}</label>
                            <input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" required maxlength="20" autocomplete="tel" class="form-control @error('phone') is-invalid @enderror">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="password" class="form-label fw-semibold">{{ __('staff.temporary_password') }}</label>
                            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
                            <div class="form-text">{{ __('staff.temporary_password_help') }}</div>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                @endisset

                <div class="mt-3">
                    <label for="role_id" class="form-label fw-semibold">{{ __('staff.role') }}</label>
                    <select id="role_id" name="role_id" required class="form-select @error('role_id') is-invalid @enderror">
                        <option value="">{{ __('staff.select_role') }}</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) old('role_id', $staffMember->role_id ?? '') === (string) $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                    <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-circle me-1"></i>{{ isset($staffMember) ? __('staff.save_role') : __('staff.create_account') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
