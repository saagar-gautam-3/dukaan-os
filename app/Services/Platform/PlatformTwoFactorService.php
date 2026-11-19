<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class PlatformTwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function qrCodeSvg(string $email, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl(config('app.name', 'Dukaan OS'), $email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 0), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    public function verifyCode(string $secret, string $code, ?int $lastStep = null): int|false
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if ($code === '' || ! ctype_digit($code)) {
            return false;
        }

        $result = $this->google2fa->verifyKeyNewer($secret, $code, $lastStep ?? 0, 1);

        if ($result === false) {
            return false;
        }

        return is_int($result) ? $result : $this->google2fa->getTimestamp();
    }

    public function currentCode(string $secret, ?int $timestamp = null): string
    {
        return $this->google2fa->getCurrentOtp($secret, $timestamp);
    }

    /**
     * @return array{plain: list<string>, hashed: list<string>}
     */
    public function generateRecoveryCodes(): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < 8; $i++) {
            $code = Str::upper(Str::random(10));
            $formatted = substr($code, 0, 5).'-'.substr($code, 5, 5);
            $plain[] = $formatted;
            $hashed[] = Hash::make($formatted);
        }

        return ['plain' => $plain, 'hashed' => $hashed];
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $code = Str::upper(trim($code));
        $hashes = $user->two_factor_recovery_codes ?? [];
        if (! is_array($hashes)) {
            return false;
        }

        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && Hash::check($code, $hash)) {
                unset($hashes[$index]);
                $user->forceFill([
                    'two_factor_recovery_codes' => array_values($hashes),
                ])->save();

                return true;
            }
        }

        return false;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        $codes = $user->two_factor_recovery_codes;

        return is_array($codes) ? count($codes) : 0;
    }
}
