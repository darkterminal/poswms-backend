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

        // Common product categories (Indonesian)
        $parentCategories = [
            ['name' => 'Elektronik', 'description' => 'Perangkat elektronik dan aksesoris'],
            ['name' => 'Pakaian & Fashion', 'description' => 'Pakaian dan aksesoris fashion'],
            ['name' => 'Rumah & Taman', 'description' => 'Perlengkapan rumah dan taman'],
            ['name' => 'Olahraga & Outdoor', 'description' => 'Peralatan olahraga dan aktivitas outdoor'],
            ['name' => 'Alat Tulis Kantor', 'description' => 'Perlengkapan kantor dan sekolah'],
            ['name' => 'Makanan & Minuman', 'description' => 'Produk makanan dan minuman'],
        ];

        $subCategories = [
            'Elektronik' => ['Komputer', 'Smartphone', 'Audio', 'Kamera', 'Aksesoris'],
            'Pakaian & Fashion' => ['Pria', 'Wanita', 'Anak', 'Sepatu', 'Aksesoris'],
            'Rumah & Taman' => ['Furniture', 'Dekorasi', 'Dapur', 'Alat Taman', 'Pencahayaan'],
            'Olahraga & Outdoor' => ['Fitness', 'Camping', 'Sepeda', 'Olahraga Tim', 'Olahraga Air'],
            'Alat Tulis Kantor' => ['Kertas', 'Pena', 'Aksesoris Meja', 'Elektronik', 'Penyimpanan'],
            'Makanan & Minuman' => ['Snack', 'Minuman', 'Makanan Kaleng', 'Bumbu', 'Sembako'],
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
