<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\Tenant;
use App\Services\OrderNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private OrderNumberGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = app(OrderNumberGenerator::class);
    }

    public function test_generate_order_number_format(): void
    {
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant->id)->create();

        $orderNumber = $this->generator->generate($order);

        // Format: APPNAME-YYYYMM-XXXX (app name may contain spaces, letters and dashes)
        // The last 3 parts should be: [name]-YYYYMM-XXXX
        $this->assertMatchesRegularExpression('/^.+-\d{6}-\d{4}$/', $orderNumber);
    }

    public function test_generate_order_number_is_sequential_for_same_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $order1 = Order::factory()->forTenant($tenant->id)->create();
        $number1 = $this->generator->generate($order1);

        $order2 = Order::factory()->forTenant($tenant->id)->create();
        $number2 = $this->generator->generate($order2);

        // Extract sequence numbers (last 4 digits)
        $seq1 = (int) substr($number1, -4);
        $seq2 = (int) substr($number2, -4);

        // Second order should have next sequence number
        $this->assertEquals(1, $seq2 - $seq1);
    }

    public function test_generate_order_number_includes_current_month(): void
    {
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant->id)->create();

        $orderNumber = $this->generator->generate($order);

        // Extract YYYYMM from the order number (second to last part)
        $currentMonth = now()->format('Ym');
        $parts = explode('-', $orderNumber);
        // Month is always second-to-last part (YYYYMM), followed by sequence (XXXX)
        $monthPart = $parts[count($parts) - 2] ?? '';

        $this->assertEquals($currentMonth, $monthPart);
    }

    public function test_generate_with_lock_format(): void
    {
        $tenant = Tenant::factory()->create();

        $orderNumber = $this->generator->generateWithLock($tenant->id);

        // Format: APPNAME-YYYYMM-XXXX (app name may contain spaces, letters and dashes)
        $this->assertMatchesRegularExpression('/^.+-\d{6}-\d{4}$/', $orderNumber);
    }

    public function test_generate_with_lock_is_sequential(): void
    {
        $tenant = Tenant::factory()->create();

        $number1 = $this->generator->generateWithLock($tenant->id);

        // Create an order to increment the count
        Order::factory()->forTenant($tenant->id)->create();

        $number2 = $this->generator->generateWithLock($tenant->id);

        // Extract sequence numbers
        $seq1 = (int) substr($number1, -4);
        $seq2 = (int) substr($number2, -4);

        // Should be sequential
        $this->assertEquals(1, $seq2 - $seq1);
    }

    public function test_generate_with_lock_starts_at_0001_for_new_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $number = $this->generator->generateWithLock($tenant->id);

        // First order should start at 0001
        $this->assertStringEndsWith('0001', $number);
    }

    public function test_generate_uses_app_name_as_prefix(): void
    {
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant->id)->create();

        $orderNumber = $this->generator->generate($order);

        // Should start with app name from config
        $appName = config('app.name', 'ORD');
        $this->assertStringStartsWith($appName . '-', $orderNumber);
    }
}
