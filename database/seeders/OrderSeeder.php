<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
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

        foreach ($tenants as $tenant) {
            $customers = $tenant->customers;
            $products = $tenant->products;
            $stores = $tenant->stores;
            $warehouses = $tenant->warehouses;

            if ($customers->isEmpty() || $products->isEmpty() || $stores->isEmpty() || $warehouses->isEmpty()) {
                continue;
            }

            $mainStore = $stores->first();
            $mainWarehouse = $warehouses->first();

            // Order statuses
            $statuses = ['pending', 'confirmed', 'processing', 'fulfilled', 'cancelled'];

            // Create sample orders
            for ($i = 0; $i < 20; $i++) {
                $customer = $customers->random();
                $orderProducts = $products->random(fake()->numberBetween(1, 5));

                $subtotal = 0;
                foreach ($orderProducts as $product) {
                    $qty = fake()->numberBetween(1, 10);
                    $subtotal += $product->price * $qty;
                }

                $status = fake()->randomElement($statuses);
                $orderNumber = 'ORD-' . $tenant->slug . '-' . strtoupper(fake()->unique()->bothify('???####'));

                $order = Order::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'store_id' => $mainStore->id,
                    'warehouse_id' => $mainWarehouse->id,
                    'order_number' => $orderNumber,
                    'status' => $status,
                    'type' => 'sale',
                    'subtotal' => $subtotal,
                    'tax' => $subtotal * 0.11, // Indonesia PPN 11%
                    'discount' => fake()->randomElement([0, 5000, 10000, 25000, 50000]),
                    'shipping' => fake()->randomElement([0, 15000, 25000, 50000]),
                    'notes' => fake()->optional(0.3)->sentence(),
                    'shipping_address' => $customer->address,
                    'shipping_city' => $customer->city,
                    'shipping_state' => $customer->state,
                    'shipping_country' => 'Indonesia',
                    'shipping_postal_code' => $customer->postal_code,
                ]);

                // Create order items
                foreach ($orderProducts as $product) {
                    $qty = fake()->numberBetween(1, 5);
                    $unitPrice = $product->price;
                    OrderItem::create([
                        'tenant_id' => $tenant->id,
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'tax' => $unitPrice * $qty * 0.11,
                        'discount' => 0,
                        'total' => $unitPrice * $qty * 1.11,
                    ]);
                }
            }

            // Create some fulfilled orders for reporting
            for ($i = 0; $i < 10; $i++) {
                $customer = $customers->random();
                $orderProducts = $products->random(fake()->numberBetween(2, 6));

                $subtotal = 0;
                foreach ($orderProducts as $product) {
                    $qty = fake()->numberBetween(1, 8);
                    $subtotal += $product->price * $qty;
                }

                $orderNumber = 'ORD-' . $tenant->slug . '-' . strtoupper(fake()->unique()->bothify('???####'));

                $order = Order::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'store_id' => $mainStore->id,
                    'warehouse_id' => $mainWarehouse->id,
                    'order_number' => $orderNumber,
                    'status' => 'fulfilled',
                    'type' => 'sale',
                    'subtotal' => $subtotal,
                    'tax' => $subtotal * 0.11,
                    'discount' => fake()->randomElement([0, 5000, 10000, 25000]),
                    'shipping' => 0,
                    'fulfilled_at' => now()->subDays(fake()->numberBetween(1, 30)),
                    'notes' => 'Pesanan selesai',
                    'shipping_address' => $customer->address,
                    'shipping_city' => $customer->city,
                    'shipping_state' => $customer->state,
                    'shipping_country' => 'Indonesia',
                    'shipping_postal_code' => $customer->postal_code,
                ]);

                // Create order items
                foreach ($orderProducts as $product) {
                    $qty = fake()->numberBetween(1, 5);
                    $unitPrice = $product->price;
                    OrderItem::create([
                        'tenant_id' => $tenant->id,
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'tax' => $unitPrice * $qty * 0.11,
                        'discount' => 0,
                        'total' => $unitPrice * $qty * 1.11,
                    ]);
                }
            }
        }
    }
}
