<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            return;
        }

        // Common product categories
        $parentCategories = [
            ['name' => 'Electronics', 'description' => 'Electronic devices and accessories'],
            ['name' => 'Clothing', 'description' => 'Apparel and fashion items'],
            ['name' => 'Home & Garden', 'description' => 'Home improvement and garden supplies'],
            ['name' => 'Sports & Outdoors', 'description' => 'Sports equipment and outdoor gear'],
            ['name' => 'Office Supplies', 'description' => 'Office and school supplies'],
            ['name' => 'Food & Beverages', 'description' => 'Consumable food and drink items'],
        ];

        $subCategories = [
            'Electronics' => ['Computers', 'Smartphones', 'Audio', 'Cameras', 'Accessories'],
            'Clothing' => ['Men', 'Women', 'Kids', 'Shoes', 'Accessories'],
            'Home & Garden' => ['Furniture', 'Decor', 'Kitchen', 'Garden Tools', 'Lighting'],
            'Sports & Outdoors' => ['Fitness', 'Camping', 'Cycling', 'Team Sports', 'Water Sports'],
            'Office Supplies' => ['Paper', 'Pens', 'Desk Accessories', 'Electronics', 'Storage'],
            'Food & Beverages' => ['Snacks', 'Beverages', 'Canned Goods', 'Condiments', 'Specialty'],
        ];

        foreach ($tenants as $tenant) {
            $createdCategories = [];

            // Helper function to create or update category
            $createOrUpdateCategory = function ($tenant, $slug, $name, $description, $parentId, $sortOrder) {
                $category = Category::where('tenant_id', $tenant->id)
                    ->where('slug', $slug)
                    ->first();

                if ($category) {
                    $category->update([
                        'name' => $name,
                        'description' => $description,
                        'parent_id' => $parentId,
                        'sort_order' => $sortOrder,
                        'active' => true,
                    ]);
                } else {
                    // Check if slug exists globally
                    $existing = Category::where('slug', $slug)->first();
                    if ($existing) {
                        $existing->update([
                            'tenant_id' => $tenant->id,
                            'name' => $name,
                            'description' => $description,
                            'parent_id' => $parentId,
                            'sort_order' => $sortOrder,
                            'active' => true,
                        ]);

                        return $existing;
                    } else {
                        return Category::create([
                            'tenant_id' => $tenant->id,
                            'name' => $name,
                            'slug' => $slug,
                            'description' => $description,
                            'parent_id' => $parentId,
                            'sort_order' => $sortOrder,
                            'active' => true,
                        ]);
                    }
                }

                return $category;
            };

            // Create parent categories
            foreach ($parentCategories as $index => $parentData) {
                $slug = Str::slug($parentData['name']);
                $category = $createOrUpdateCategory(
                    $tenant,
                    $slug,
                    $parentData['name'],
                    $parentData['description'],
                    null,
                    $index + 1
                );
                $createdCategories[$parentData['name']] = $category->id;
            }

            // Create sub-categories
            foreach ($subCategories as $parentName => $subNames) {
                $parentId = $createdCategories[$parentName] ?? null;

                if (! $parentId) {
                    continue;
                }

                foreach ($subNames as $index => $subName) {
                    $slug = Str::slug($subName);
                    $createOrUpdateCategory(
                        $tenant,
                        $slug,
                        $subName,
                        "{$subName} products",
                        $parentId,
                        $index + 1
                    );
                }
            }

            // Create additional random categories with unique slugs per tenant
            for ($i = 0; $i < 5; $i++) {
                $randomName = fake()->word();
                $slug = $tenant->slug . '-cat-' . Str::slug($randomName) . '-' . $i . '-' . time();

                Category::create([
                    'tenant_id' => $tenant->id,
                    'name' => $randomName,
                    'slug' => $slug,
                    'description' => fake()->sentence(),
                    'parent_id' => null,
                    'sort_order' => 0,
                    'active' => true,
                ]);
            }
        }
    }
}
