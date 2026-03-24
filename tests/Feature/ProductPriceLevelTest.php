<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPriceLevel;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPriceLevelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->forTenant($this->tenant->id)->create([
            'price' => 10000,
            'cost' => 8000,
        ]);
    }

    public function test_product_can_have_price_levels(): void
    {
        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->baseUnit()
            ->create(['price' => 10000]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->pack(10)
            ->create(['price' => 95000]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->carton(100)
            ->create(['price' => 900000]);

        $this->assertTrue($this->product->fresh()->hasPriceLevels());
        $this->assertEquals(3, $this->product->priceLevels()->count());
    }

    public function test_get_price_for_level(): void
    {
        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->baseUnit()
            ->create(['level_name' => 'piece', 'price' => 24000]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->pack(10)
            ->create(['level_name' => 'pack', 'price' => 236000]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->carton(200)
            ->create(['level_name' => 'carton', 'price' => 24500000]);

        $this->assertEquals(24000.00, $this->product->getPriceForLevel('piece'));
        $this->assertEquals(236000.00, $this->product->getPriceForLevel('pack'));
        $this->assertEquals(24500000.00, $this->product->getPriceForLevel('carton'));
        $this->assertNull($this->product->getPriceForLevel('nonexistent'));
    }

    public function test_get_price_for_order(): void
    {
        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->create(['level_order' => 1, 'price' => 24000]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->create(['level_order' => 2, 'price' => 236000]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->create(['level_order' => 3, 'price' => 24500000]);

        $this->assertEquals(24000.00, $this->product->getPriceForOrder(1));
        $this->assertEquals(236000.00, $this->product->getPriceForOrder(2));
        $this->assertEquals(24500000.00, $this->product->getPriceForOrder(3));
        $this->assertNull($this->product->getPriceForOrder(99));
    }

    public function test_get_all_price_levels(): void
    {
        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->baseUnit()
            ->create([
                'level_name' => 'piece',
                'level_order' => 1,
                'unit_size' => 1,
                'price' => 24000,
                'cost' => 20000,
                'barcode' => '123456789',
            ]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->pack(10)
            ->create([
                'level_name' => 'pack',
                'level_order' => 2,
                'unit_size' => 10,
                'price' => 236000,
                'cost' => 196000,
                'barcode' => '987654321',
            ]);

        $levels = $this->product->getAllPriceLevels();

        $this->assertCount(2, $levels);
        $this->assertEquals('piece', $levels[0]['level_name']);
        $this->assertEquals(24000.00, $levels[0]['price']);
        $this->assertEquals(24000.00, $levels[0]['price_per_base_unit']);
        $this->assertEquals('pack', $levels[1]['level_name']);
        $this->assertEquals(236000.00, $levels[1]['price']);
        $this->assertEquals(23600.00, $levels[1]['price_per_base_unit']);
    }

    public function test_calculate_price_for_quantity_with_price_levels(): void
    {
        // Sampoerna example: piece=24000, pack(10)=236000, carton(200)=24500000
        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->baseUnit()
            ->create(['level_name' => 'piece', 'unit_size' => 1, 'price' => 24000]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->pack(10)
            ->create(['level_name' => 'pack', 'unit_size' => 10, 'price' => 236000]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->carton(200)
            ->create(['level_name' => 'carton', 'unit_size' => 200, 'price' => 24500000]);

        // 1 piece = 24000
        $this->assertEquals(24000.00, $this->product->calculatePriceForQuantity(1));

        // 10 pieces = 1 pack = 236000
        $this->assertEquals(236000.00, $this->product->calculatePriceForQuantity(10));

        // 11 pieces = 1 pack + 1 piece = 236000 + 24000 = 260000
        $this->assertEquals(260000.00, $this->product->calculatePriceForQuantity(11));

        // 200 pieces = 1 carton = 24500000
        $this->assertEquals(24500000.00, $this->product->calculatePriceForQuantity(200));

        // 211 pieces = 1 carton + 1 pack + 1 piece = 24500000 + 236000 + 24000 = 24760000
        $this->assertEquals(24760000.00, $this->product->calculatePriceForQuantity(211));
    }

    public function test_calculate_price_for_quantity_without_price_levels(): void
    {
        // Product with no price levels should use base price
        $this->assertEquals(10000.00, $this->product->calculatePriceForQuantity(1));
        $this->assertEquals(50000.00, $this->product->calculatePriceForQuantity(5));
        $this->assertEquals(100000.00, $this->product->calculatePriceForQuantity(10));
    }

    public function test_inactive_price_levels_are_excluded(): void
    {
        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->baseUnit()
            ->create(['level_name' => 'piece', 'price' => 24000, 'active' => true]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->pack(10)
            ->create(['level_name' => 'pack', 'price' => 236000, 'active' => false]);

        $this->assertEquals(24000.00, $this->product->getPriceForLevel('piece'));
        $this->assertNull($this->product->getPriceForLevel('pack'));

        $levels = $this->product->getAllPriceLevels();
        $this->assertCount(1, $levels);
        $this->assertEquals('piece', $levels[0]['level_name']);
    }

    public function test_price_level_get_price_per_base_unit(): void
    {
        $priceLevel = ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->pack(10)
            ->create(['price' => 236000]);

        $this->assertEquals(23600.00, $priceLevel->getPricePerBaseUnit());

        $priceLevel2 = ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->carton(200)
            ->create(['price' => 24500000]);

        $this->assertEquals(122500.00, $priceLevel2->getPricePerBaseUnit());
    }

    public function test_price_level_is_base_unit(): void
    {
        $baseLevel = ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->baseUnit()
            ->create();

        $packLevel = ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($this->product->id)
            ->pack(10)
            ->create();

        $this->assertTrue($baseLevel->isBaseUnit());
        $this->assertFalse($packLevel->isBaseUnit());
    }

    public function test_sampoerna_example(): void
    {
        // Create the exact Sampoerna example from the user's request
        $sampoerna = Product::factory()->forTenant($this->tenant->id)->create([
            'name' => 'Sampoerna Prima Kretek',
            'sku' => 'SAM-PRM-001',
            'price' => 24000,
        ]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($sampoerna->id)
            ->create([
                'level_name' => 'piece',
                'level_order' => 1,
                'unit_size' => 1,
                'price' => 24000,
            ]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($sampoerna->id)
            ->create([
                'level_name' => 'pack',
                'level_order' => 2,
                'unit_size' => 10,
                'price' => 236000,
            ]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($sampoerna->id)
            ->create([
                'level_name' => 'carton',
                'level_order' => 3,
                'unit_size' => 200,
                'price' => 24500000,
            ]);

        // Verify individual prices
        $this->assertEquals(24000, $sampoerna->getPriceForLevel('piece'));
        $this->assertEquals(236000, $sampoerna->getPriceForLevel('pack'));
        $this->assertEquals(24500000, $sampoerna->getPriceForLevel('carton'));

        // Verify bulk calculations
        $this->assertEquals(24000, $sampoerna->calculatePriceForQuantity(1));
        $this->assertEquals(236000, $sampoerna->calculatePriceForQuantity(10));
        $this->assertEquals(24500000, $sampoerna->calculatePriceForQuantity(200));

        // Verify mixed quantities
        $this->assertEquals(236000 + 24000, $sampoerna->calculatePriceForQuantity(11));
        $this->assertEquals(24500000 + 236000, $sampoerna->calculatePriceForQuantity(210));
    }

    public function test_backward_compatibility_with_base_price(): void
    {
        // Product without price levels should still work with base price
        $simpleProduct = Product::factory()->forTenant($this->tenant->id)->create([
            'price' => 50000,
        ]);

        $this->assertFalse($simpleProduct->hasPriceLevels());
        $this->assertEquals(50000, $simpleProduct->calculatePriceForQuantity(1));
        $this->assertEquals(250000, $simpleProduct->calculatePriceForQuantity(5));
    }

    public function test_four_price_levels(): void
    {
        // Test product with 4 price levels
        $product = Product::factory()->forTenant($this->tenant->id)->create([
            'name' => 'Luxury Whiskey',
            'sku' => 'LUX-WHN-001',
            'price' => 750000,
        ]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($product->id)
            ->create([
                'level_name' => 'bottle',
                'level_order' => 1,
                'unit_size' => 1,
                'price' => 750000,
            ]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($product->id)
            ->create([
                'level_name' => 'pair',
                'level_order' => 2,
                'unit_size' => 2,
                'price' => 1450000,
            ]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($product->id)
            ->create([
                'level_name' => 'half_case',
                'level_order' => 3,
                'unit_size' => 6,
                'price' => 4200000,
            ]);

        ProductPriceLevel::factory()
            ->forTenant($this->tenant->id)
            ->forProduct($product->id)
            ->create([
                'level_name' => 'case',
                'level_order' => 4,
                'unit_size' => 12,
                'price' => 8100000,
            ]);

        $this->assertTrue($product->hasPriceLevels());
        $this->assertEquals(4, $product->priceLevels()->count());

        // Verify all levels
        $this->assertEquals(750000, $product->getPriceForLevel('bottle'));
        $this->assertEquals(1450000, $product->getPriceForLevel('pair'));
        $this->assertEquals(4200000, $product->getPriceForLevel('half_case'));
        $this->assertEquals(8100000, $product->getPriceForLevel('case'));

        // Verify calculations
        $this->assertEquals(750000, $product->calculatePriceForQuantity(1));
        $this->assertEquals(1450000, $product->calculatePriceForQuantity(2));
        $this->assertEquals(4200000, $product->calculatePriceForQuantity(6));
        $this->assertEquals(8100000, $product->calculatePriceForQuantity(12));
        $this->assertEquals(8100000 + 750000, $product->calculatePriceForQuantity(13));
    }
}
