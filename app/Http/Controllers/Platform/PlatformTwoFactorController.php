<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformTwoFactorService;
use App\Support\AuditTrail;
use App\Support\PlatformAuditEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformTwoFactorController extends Controller
{
    public function __construct(private readonly PlatformTwoFactorService $twoFactor) {}

    public function showSetup(Request $request): View|RedirectResponse
    {
        $this->authorize('platform.manage');

        $user = $request->user();

        if ($user->hasPlatformTwoFactorConfirmed()) {
            return redirect()->route('platform.shops.index');
        }

        $secret = $this->twoFactor->generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step' => null,
        ])->save();

        $qrSvg = $this->twoFactor->qrCodeSvg($user->email, $secret);

        return view('platform.two-factor.setup', [
            'qrSvg' => $qrSvg,
            'secret' => $secret,
        ]);
    }

    public function confirmSetup(Request $request): RedirectResponse|View
    {
        $this->authorize('platform.manage');

        $user = $request->user();
        $user->refresh();

        if ($user->hasPlatformTwoFactorConfirmed()) {
            return redirect()->route('platform.shops.index');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $secret = $user->two_factor_secret;
        if (! is_string($secret) || $secret === '') {
            return redirect()->route('platform.2fa.setup')->withErrors([
                'code' => __('platform.two_factor_setup_expired'),
            ]);
        }

        $step = $this->twoFactor->verifyCode($secret, (string) $validated['code'], $user->two_factor_last_step);
        if ($step === false) {
            AuditTrail::record($user, $user->id, PlatformAuditEvents::TWO_FACTOR_FAILED, null, [
                'context' => 'setup',
            ]);

            throw ValidationException::withMessages([
                'code' => __('platform.two_factor_invalid_code'),
            ]);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes['hashed'],
            'two_factor_last_step' => $step,
        ])->save();

        AuditTrail::record($user, $user->id, PlatformAuditEvents::TWO_FACTOR_ENABLED, null, [
            'enabled' => true,
        ]);

        $request->session()->regenerate();
        $request->session()->put('platform_2fa_passed_at', now()->timestamp);
        $request->session()->flash('platform_recovery_codes_once', $codes['plain']);

        return redirect()->route('platform.2fa.recovery-codes');
    }

    public function showChallenge(Request $request): View|RedirectResponse
    {
        $this->authorize('platform.manage');

        $user = $request->user();

        if (! $user->hasPlatformTwoFactorConfirmed()) {
            return redirect()->route('platform.2fa.setup');
        }

        return view('platform.two-factor.challenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $this->authorize('platform.manage');

        $user = $request->user();
        $user->refresh();

        if (! $user->hasPlatformTwoFactorConfirmed()) {
            return redirect()->route('platform.2fa.setup');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $code = trim((string) $validated['code']);

        // Try TOTP first when it looks like a 6-digit code.
        $digits = preg_replace('/\s+/', '', $code) ?? '';
        if (ctype_digit($digits) && strlen($digits) === 6) {
            $step = $this->twoFactor->verifyCode((string) $user->two_factor_secret, $digits, $user->two_factor_last_step);
            if ($step !== false) {
                $user->forceFill(['two_factor_last_step' => $step])->save();
                $request->session()->regenerate();
                $request->session()->put('platform_2fa_passed_at', now()->timestamp);

                return redirect()->intended(route('platform.shops.index'));
            }
        }

        // Try recovery code.
        $normalizedRecovery = strtoupper($code);
        if ($this->twoFactor->consumeRecoveryCode($user, $normalizedRecovery)) {
            AuditTrail::record($user, $user->id, PlatformAuditEvents::TWO_FACTOR_RECOVERY_USED, null, [
                'remaining' => $this->twoFactor->remainingRecoveryCodes($user),
            ]);

            $request->session()->regenerate();
            $request->session()->put('platform_2fa_passed_at', now()->timestamp);

            $remaining = $this->twoFactor->remainingRecoveryCodes($user);
            $response = redirect()->intended(route('platform.shops.index'));
            if ($remaining <= 2) {
                $response->with('warning', __('platform.two_factor_recovery_codes_low', ['count' => $remaining]));
            }

            return $response;
        }

        AuditTrail::record($user, $user->id, PlatformAuditEvents::TWO_FACTOR_FAILED, null, [
            'context' => 'challenge',
        ]);

        throw ValidationException::withMessages([
            'code' => __('platform.two_factor_invalid_code'),
        ]);
    }

    public function showRecoveryCodes(Request $request): View|RedirectResponse
    {
        $this->authorize('platform.manage');

        $user = $request->user();

        if (! $user->hasPlatformTwoFactorConfirmed()) {
            return redirect()->route('platform.2fa.setup');
        }

        $once = $request->session()->get('platform_recovery_codes_once');
        $codes = is_array($once) ? array_values(array_filter($once, 'is_string')) : null;

        return view('platform.two-factor.recovery-codes', [
            'recoveryCodes' => $codes,
            'remaining' => $this->twoFactor->remainingRecoveryCodes($user),
        ]);
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $this->authorize('platform.manage');

        $user = $request->user();
        $user->refresh();

        if (! $user->hasPlatformTwoFactorConfirmed()) {
            return redirect()->route('platform.2fa.setup');
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        if (! Hash::check((string) $validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $step = $this->twoFactor->verifyCode((string) $user->two_factor_secret, (string) $validated['code'], $user->two_factor_last_step);
        if ($step === false) {
            AuditTrail::record($user, $user->id, PlatformAuditEvents::TWO_FACTOR_FAILED, null, [
                'context' => 'regenerate',
            ]);

            throw ValidationException::withMessages([
                'code' => __('platform.two_factor_invalid_code'),
            ]);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => $codes['hashed'],
            'two_factor_last_step' => $step,
        ])->save();

        AuditTrail::record($user, $user->id, PlatformAuditEvents::TWO_FACTOR_RECOVERY_REGENERATED, null, [
            'regenerated' => true,
        ]);

        $request->session()->regenerate();
        $request->session()->put('platform_2fa_passed_at', now()->timestamp);
        $request->session()->flash('platform_recovery_codes_once', $codes['plain']);

        return redirect()->route('platform.2fa.recovery-codes');
    }
}
