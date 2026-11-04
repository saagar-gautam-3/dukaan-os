@props([
    'id',
    'name',
    'autocomplete' => 'new-password',
    'suggest' => false,
    'confirmationId' => null,
])

<div class="password-field" data-password-widget data-password-confirmation="{{ $confirmationId }}">
    <div class="input-group">
        @isset($prepend)
            {{ $prepend }}
        @endisset
        <input {{ $attributes->merge([
            'id' => $id,
            'name' => $name,
            'type' => 'password',
            'autocomplete' => $autocomplete,
        ])->class(['form-control']) }} data-password-input>
        <button type="button" class="btn btn-outline-secondary password-toggle" data-password-toggle
                data-show-label="{{ __('ui.show_password') }}" data-hide-label="{{ __('ui.hide_password') }}"
                aria-label="{{ __('ui.show_password') }}" aria-pressed="false" aria-controls="{{ $id }}">
            <i class="bi bi-eye" aria-hidden="true"></i>
        </button>
    </div>

    @if ($suggest)
        <div class="password-suggestion mt-2">
            <button type="button" class="btn btn-outline-secondary btn-sm password-generate" data-password-generate>
                <i class="bi bi-magic me-1" aria-hidden="true"></i>{{ __('ui.suggest_strong_password') }}
            </button>
            <span class="form-text password-strength ms-2" data-password-strength
                  data-weak-label="{{ __('ui.password_strength_weak') }}"
                  data-medium-label="{{ __('ui.password_strength_medium') }}"
                  data-strong-label="{{ __('ui.password_strength_strong') }}"
                  data-hint-label="{{ __('ui.password_strength_hint') }}"
                  aria-live="polite">{{ __('ui.password_strength_hint') }}</span>
        </div>
        <span class="visually-hidden" data-password-generated data-generated-label="{{ __('ui.password_generated') }}" role="status" aria-live="polite"></span>
    @endif
</div>
