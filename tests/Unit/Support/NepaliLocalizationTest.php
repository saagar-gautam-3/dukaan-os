<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Shop;
use App\Support\BikramSambat;
use App\Support\FiscalYear;
use App\Support\NepaliNumber;
use App\Support\NepaliPhone;
use App\Tenancy\TenantContext;
use Tests\TestCase;

class NepaliLocalizationTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_lakh_crore_formatting_groups_correctly(): void
    {
        $this->assertEquals('1,000.00', NepaliNumber::formatLakhCrore(1000));
        $this->assertEquals('10,000.00', NepaliNumber::formatLakhCrore(10000));
        $this->assertEquals('1,00,000.00', NepaliNumber::formatLakhCrore(100000));
        $this->assertEquals('25,50,000.00', NepaliNumber::formatLakhCrore(2550000));
        $this->assertEquals('1,00,00,000.00', NepaliNumber::formatLakhCrore(10000000));
        $this->assertEquals('-1,50,000.50', NepaliNumber::formatLakhCrore(-150000.5));
    }

    public function test_devanagari_and_ascii_digit_conversion(): void
    {
        $ascii = '0123456789';
        $devanagari = '०१२३४५६७८९';

        $this->assertEquals($devanagari, NepaliNumber::toDevanagari($ascii));
        $this->assertEquals($ascii, NepaliNumber::toAscii($devanagari));
        $this->assertEquals('रु. १,००,०००.००', NepaliNumber::toDevanagari('रु. 1,00,000.00'));
        $this->assertEquals('Rs. 1000', NepaliNumber::toAscii('Rs. १०००'));
    }

    public function test_format_npr_outputs_expected_string(): void
    {
        // In English locale
        $this->assertEquals('Rs. 1,00,000.00', NepaliNumber::formatNpr(100000, 'en'));

        // In Nepali locale
        $this->assertEquals('रु. १,००,०००.००', NepaliNumber::formatNpr(100000, 'ne'));

        // Without symbol
        $this->assertEquals('1,00,000.00', NepaliNumber::formatNpr(100000, 'en', false));
        $this->assertEquals('१,००,०००.००', NepaliNumber::formatNpr(100000, 'ne', false));
    }

    public function test_shop_setting_overrides_digit_display(): void
    {
        $shop = new Shop([
            'settings' => ['devanagari_digits' => false],
        ]);
        TenantContext::setShop($shop);

        // Even with Nepali locale, ASCII digits are displayed when shop explicitly disables devanagari_digits
        $this->assertEquals('रु. 1,00,000.00', NepaliNumber::formatNpr(100000, 'ne'));

        $shop->settings = ['devanagari_digits' => true];
        // Even with English locale, Devanagari digits are displayed when shop explicitly enables devanagari_digits
        $this->assertEquals('Rs. १,००,०००.००', NepaliNumber::formatNpr(100000, 'en'));
    }

    public function test_nepali_phone_normalisation_and_validation(): void
    {
        // Strip country code +977 or 977
        $this->assertEquals('9841234567', NepaliPhone::normalize('+9779841234567'));
        $this->assertEquals('9841234567', NepaliPhone::normalize('9779841234567'));

        // Strip leading 0
        $this->assertEquals('9841234567', NepaliPhone::normalize('09841234567'));

        // Strip spaces, dashes, symbols
        $this->assertEquals('9841234567', NepaliPhone::normalize('984-123-4567'));
        $this->assertEquals('9841234567', NepaliPhone::normalize('+977 984 123 4567'));

        // Devanagari numerals
        $this->assertEquals('9841234567', NepaliPhone::normalize('९८४१२३४५६७'));
        $this->assertEquals('9841234567', NepaliPhone::normalize('+९७७-९८४१२३४५६७'));

        // Validation
        $this->assertTrue(NepaliPhone::isValidMobile('9841234567'));
        $this->assertTrue(NepaliPhone::isValidMobile('+9779812345678'));
        $this->assertTrue(NepaliPhone::isValidMobile('९८४१२३४५६७'));
        $this->assertFalse(NepaliPhone::isValidMobile('1234567890')); // does not start with 9
        $this->assertFalse(NepaliPhone::isValidMobile('98412345')); // too short
        $this->assertFalse(NepaliPhone::isValidMobile(null));
    }

    public function test_bikram_sambat_official_conversions_including_boundaries(): void
    {
        // 1. Year boundary (Baisakh 1, 2081 BS <-> 2024-04-13 AD)
        $this->assertEquals('2081-01-01', BikramSambat::toBs('2024-04-13'));
        $this->assertEquals('2024-04-13', BikramSambat::toAd('2081-01-01'));

        // 2. Fiscal year boundary start (Shrawan 1, 2081 BS <-> 2024-07-16 AD)
        $this->assertEquals('2081-04-01', BikramSambat::toBs('2024-07-16'));
        $this->assertEquals('2024-07-16', BikramSambat::toAd('2081-04-01'));

        // 3. Fiscal year boundary end (Ashadh 31, 2081 BS <-> 2024-07-15 AD)
        $this->assertEquals('2081-03-31', BikramSambat::toBs('2024-07-15'));
        $this->assertEquals('2024-07-15', BikramSambat::toAd('2081-03-31'));

        // 4. Year boundary end (Chaitra 30, 2080 BS <-> 2024-04-12 AD)
        $this->assertEquals('2080-12-30', BikramSambat::toBs('2024-04-12'));
        $this->assertEquals('2024-04-12', BikramSambat::toAd('2080-12-30'));

        // 5. Historical benchmark: 2015-04-25 AD (Nepal Earthquake) <-> 2072-01-12 BS
        $this->assertEquals('2072-01-12', BikramSambat::toBs('2015-04-25'));
        $this->assertEquals('2015-04-25', BikramSambat::toAd('2072-01-12'));
    }

    public function test_fiscal_year_computation_starts_on_shrawan_one(): void
    {
        // Prior to Shrawan 1, 2081 (Baisakh 1, 2081) is in FY 2080-81
        $this->assertEquals('2080-81', FiscalYear::forDate('2024-04-13'));

        // Ashadh 31, 2081 (last day of Ashadh) is in FY 2080-81
        $this->assertEquals('2080-81', FiscalYear::forDate('2024-07-15'));

        // Shrawan 1, 2081 starts the new FY 2081-82
        $this->assertEquals('2081-82', FiscalYear::forDate('2024-07-16'));

        // Chaitra 30, 2081 (2025-04-13) is in FY 2081-82
        $this->assertEquals('2081-82', FiscalYear::forDate('2025-04-13'));
    }
}
