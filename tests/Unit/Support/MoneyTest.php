<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Money;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

final class MoneyTest extends TestCase
{
    public function test_decimal_string_arithmetic_preserves_large_values_and_scale(): void
    {
        $this->assertSame('100000000000001.00', Money::add('99999999999999.99', '1.01'));
        $this->assertSame('125.00', Money::multiply('12.500', '10.00'));
        $this->assertSame('3.33', Money::divide('10.00', '3.00'));
        $this->assertSame(1, Money::compare('100000000000000.01', '100000000000000.00'));
    }

    public function test_round_uses_decimal_half_up_without_float_conversion(): void
    {
        $this->assertSame('1.01', Money::round('1.005', 2));
        $this->assertSame('-1.01', Money::round('-1.005', 2));
        $this->assertSame('100000000000000.00', Money::round('99999999999999.999', 2));
    }

    public function test_invalid_decimal_message_uses_the_active_locale_translation(): void
    {
        App::setLocale('ne');
        try {
            Money::round('not-a-decimal');
            $this->fail('Invalid decimal input should throw.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(__('errors.invalid_decimal_string'), $exception->getMessage());
        } finally {
            App::setLocale(config('app.locale'));
        }
    }
}
