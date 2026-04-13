<?php

namespace Tests\Unit;

use App\Models\CurrencyExchangeRate;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\MoneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Money\Money;
use Tests\TestCase;

class MoneyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MoneyService $moneyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moneyService = app(MoneyService::class);
    }

    public function test_make_creates_money_object(): void
    {
        $money = $this->moneyService->make(100.50, 'USD');

        $this->assertInstanceOf(Money::class, $money);
        $this->assertEquals('USD', $money->getCurrency()->getCode());
        $this->assertEquals('100.50', $this->moneyService->formatDecimal($money));
    }

    public function test_make_from_minor_units(): void
    {
        $money = $this->moneyService->makeFromMinorUnits(10050, 'USD');

        $this->assertEquals('100.50', $this->moneyService->formatDecimal($money));
    }

    public function test_parse_decimal_string(): void
    {
        $money = $this->moneyService->parse('99.99', 'EUR');

        $this->assertInstanceOf(Money::class, $money);
        $this->assertEquals('EUR', $money->getCurrency()->getCode());
        $this->assertEquals('99.99', $this->moneyService->formatDecimal($money));
    }

    public function test_format_decimal(): void
    {
        $money = $this->moneyService->make(1234.56, 'USD');

        $this->assertEquals('1234.56', $this->moneyService->formatDecimal($money));
    }

    public function test_format_with_locale(): void
    {
        $money = $this->moneyService->make(1234.56, 'USD');

        $formatted = $this->moneyService->format($money, 'en_US');
        $this->assertStringContainsString('1,234.56', $formatted);
    }

    public function test_get_default_currency(): void
    {
        $default = $this->moneyService->getDefaultCurrency();
        $this->assertEquals('USD', $default);
    }

    public function test_get_default_currency_from_settings(): void
    {
        Setting::set('application.default_currency', 'EUR');

        $this->assertEquals('EUR', $this->moneyService->getDefaultCurrency());
    }

    public function test_get_tenant_currency(): void
    {
        $tenant = Tenant::factory()->create(['currency' => 'IDR']);

        $this->assertEquals('IDR', $this->moneyService->getTenantCurrency($tenant));
    }

    public function test_get_tenant_currency_fallback(): void
    {
        $tenant = Tenant::factory()->create(['currency' => '']);

        $this->assertEquals('USD', $this->moneyService->getTenantCurrency($tenant));
    }

    public function test_convert_same_currency(): void
    {
        $money = $this->moneyService->make(100, 'USD');
        $converted = $this->moneyService->convert($money, 'USD');

        $this->assertEquals($money, $converted);
    }

    public function test_convert_with_exchange_rate(): void
    {
        CurrencyExchangeRate::updateRate('USD', 'EUR', 0.92, 'manual');

        $money = $this->moneyService->make(100, 'USD');
        $converted = $this->moneyService->convert($money, 'EUR');

        $expected = $this->moneyService->make(92, 'EUR');
        $this->assertEquals(
            $this->moneyService->formatDecimal($expected),
            $this->moneyService->formatDecimal($converted)
        );
    }

    public function test_convert_throws_when_no_rate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $money = $this->moneyService->make(100, 'USD');
        $this->moneyService->convert($money, 'XYZ');
    }

    public function test_convert_amount_helper(): void
    {
        CurrencyExchangeRate::updateRate('USD', 'EUR', 0.92, 'manual');

        $result = $this->moneyService->convertAmount(100, 'USD', 'EUR');
        $this->assertEqualsWithDelta(92.0, $result, 0.01);
    }

    public function test_get_available_currencies(): void
    {
        $currencies = $this->moneyService->getAvailableCurrencies();

        $this->assertIsArray($currencies);
        $this->assertArrayHasKey('USD', $currencies);
        $this->assertArrayHasKey('EUR', $currencies);
        $this->assertArrayHasKey('IDR', $currencies);
    }

    public function test_get_currency_info(): void
    {
        $info = $this->moneyService->getCurrencyInfo('USD');

        $this->assertNotNull($info);
        $this->assertEquals('USD', $info['code']);
        $this->assertEquals(2, $info['decimal_places']);
    }

    public function test_get_currency_info_invalid(): void
    {
        $info = $this->moneyService->getCurrencyInfo('INVALID');
        $this->assertNull($info);
    }

    public function test_make_for_tenant(): void
    {
        $tenant = Tenant::factory()->create(['currency' => 'EUR']);

        $money = $this->moneyService->makeForTenant(50, $tenant);

        $this->assertEquals('EUR', $money->getCurrency()->getCode());
        $this->assertEquals('50.00', $this->moneyService->formatDecimal($money));
    }

    public function test_exchange_rate_inverse(): void
    {
        CurrencyExchangeRate::updateRate('USD', 'EUR', 0.92, 'manual');

        $direct = $this->moneyService->getExchangeRate('USD', 'EUR');
        $inverse = $this->moneyService->getExchangeRate('EUR', 'USD');

        $this->assertEqualsWithDelta(0.92, $direct, 0.0001);
        $this->assertNotNull($inverse);
        $this->assertGreaterThan(1, $inverse);
    }

    public function test_exchange_rate_cross_rate(): void
    {
        CurrencyExchangeRate::updateRate('USD', 'EUR', 0.92, 'manual');
        CurrencyExchangeRate::updateRate('USD', 'IDR', 15650, 'manual');

        $rate = $this->moneyService->getExchangeRate('EUR', 'IDR');

        $this->assertNotNull($rate);
        // EUR->IDR = USD->IDR / USD->EUR = 15650 / 0.92
        $this->assertGreaterThan(15000, $rate);
    }
}
