<?php

namespace Tests\Unit;

use App\Models\CurrencyExchangeRate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Money\Money;
use Tests\TestCase;

class HasMoneyTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_as_money(): void
    {
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->forTenant($tenant->id)->create([
            'price' => 99.99,
            'cost' => 50.00,
        ]);

        // Debug: check currency resolution via reflection
        $method = new \ReflectionMethod($product, 'getMoneyCurrency');
        $resolvedCurrency = $method->invoke($product);
        $this->assertEquals('IDR', $resolvedCurrency, "Expected IDR but got {$resolvedCurrency}");

        $money = $product->priceAsMoney();

        // Debug: check money object currency
        $this->assertEquals('IDR', $money->getCurrency()->getCode(), 'Money currency should be IDR');

        $this->assertInstanceOf(Money::class, $money);
        $this->assertEquals('99.99', app(\App\Services\MoneyService::class)->formatDecimal($money));
    }

    public function test_product_format_price(): void
    {
        $tenant = Tenant::factory()->create(['currency' => 'USD']);
        $product = Product::factory()->for($tenant)->create([
            'price' => 1234.56,
        ]);

        $formatted = $product->formatPrice();

        $this->assertStringContainsString('1,234.56', $formatted);
    }

    public function test_product_format_price_decimal(): void
    {
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->forTenant($tenant->id)->create([
            'price' => 1234.56,
        ]);

        $decimal = $product->formatPriceDecimal();

        $this->assertEquals('1234.56', $decimal);
    }

    public function test_product_convert_price(): void
    {
        CurrencyExchangeRate::updateRate('USD', 'EUR', 0.92, 'manual');
        CurrencyExchangeRate::updateRate('USD', 'IDR', 15650, 'manual');

        $tenant = Tenant::factory()->create();
        $product = Product::factory()->forTenant($tenant->id)->create([
            'price' => 100.00,
        ]);

        $converted = $product->convertPrice('EUR');

        $this->assertInstanceOf(Money::class, $converted);
        $this->assertEquals('EUR', $converted->getCurrency()->getCode());
    }

    public function test_order_as_money(): void
    {
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant->id)->create([
            'subtotal' => 500.00,
            'tax' => 50.00,
            'total' => 550.00,
        ]);

        $subtotalMoney = $order->subtotalAsMoney();
        $taxMoney = $order->taxAsMoney();

        $this->assertInstanceOf(Money::class, $subtotalMoney);
        $this->assertInstanceOf(Money::class, $taxMoney);
        $this->assertEquals('500.00', app(\App\Services\MoneyService::class)->formatDecimal($subtotalMoney));
        $this->assertEquals('50.00', app(\App\Services\MoneyService::class)->formatDecimal($taxMoney));
    }

    public function test_as_money_throws_for_invalid_field(): void
    {
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        $this->expectException(\InvalidArgumentException::class);
        $product->asMoney('nonexistent_field');
    }

    public function test_money_uses_tenant_currency(): void
    {
        $tenant = Tenant::factory()->create(['currency' => 'EUR']);
        $product = Product::factory()->for($tenant)->create(['price' => 100]);

        $money = $product->priceAsMoney();

        $this->assertEquals('EUR', $money->getCurrency()->getCode());
    }
}
