<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get available statuses
        $pendingStatus = OrderStatus::where('name', 'pending')->first();
        $confirmedStatus = OrderStatus::where('name', 'confirmed')->first();
        $deliveredStatus = OrderStatus::where('name', 'delivered')->first();
        $cancelledStatus = OrderStatus::where('name', 'cancelled')->first();

        // Sample orders data
        $orders = [
            [
                'user_id' => 1,
                'billing_address_id' => 1,
                'shipping_address_id' => 1,
                'status_id' => $pendingStatus->id,
                'notes' => 'Première commande de test',
                'total_discount' => 0,
                'items' => [
                    [
                        'product_id' => 1,
                        'quantity' => 2,
                        'unit_price_ht' => 49.99,
                        'unit_price_ttc' => 59.99,
                        'vat_rate' => 20.0,
                        'product_name' => 'iPhone 15 Pro',
                        'product_ref' => 'IPH15-PRO-256',
                    ],
                    [
                        'product_id' => 2,
                        'quantity' => 1,
                        'unit_price_ht' => 29.99,
                        'unit_price_ttc' => 35.99,
                        'vat_rate' => 20.0,
                        'product_name' => 'Coque iPhone 15 Pro',
                        'product_ref' => 'CASE-IPH15-PRO',
                    ],
                ]
            ],
            [
                'user_id' => 1,
                'billing_address_id' => 1,
                'shipping_address_id' => 1,
                'status_id' => $confirmedStatus->id,
                'notes' => 'Commande confirmée rapidement',
                'total_discount' => 10.0,
                'items' => [
                    [
                        'product_id' => 3,
                        'quantity' => 1,
                        'unit_price_ht' => 999.99,
                        'unit_price_ttc' => 1199.99,
                        'vat_rate' => 20.0,
                        'product_name' => 'MacBook Pro M3',
                        'product_ref' => 'MBP-M3-16-512',
                    ],
                ]
            ],
            [
                'user_id' => 2,
                'billing_address_id' => 2,
                'shipping_address_id' => 2,
                'status_id' => $deliveredStatus->id,
                'notes' => 'Commande livrée avec succès',
                'total_discount' => 0,
                'items' => [
                    [
                        'product_id' => 4,
                        'quantity' => 3,
                        'unit_price_ht' => 19.99,
                        'unit_price_ttc' => 23.99,
                        'vat_rate' => 20.0,
                        'product_name' => 'Écouteurs sans fil',
                        'product_ref' => 'AIRP-BASIC',
                    ],
                    [
                        'product_id' => 5,
                        'quantity' => 1,
                        'unit_price_ht' => 79.99,
                        'unit_price_ttc' => 95.99,
                        'vat_rate' => 20.0,
                        'product_name' => 'Chargeur sans fil',
                        'product_ref' => 'CHARGE-WIRELESS',
                    ],
                ]
            ],
            [
                'user_id' => 1,
                'billing_address_id' => 1,
                'shipping_address_id' => 1,
                'status_id' => $cancelledStatus->id,
                'notes' => 'Commande annulée par le client',
                'total_discount' => 0,
                'items' => [
                    [
                        'product_id' => 6,
                        'quantity' => 1,
                        'unit_price_ht' => 199.99,
                        'unit_price_ttc' => 239.99,
                        'vat_rate' => 20.0,
                        'product_name' => 'Apple Watch Series 9',
                        'product_ref' => 'AW-S9-41MM',
                    ],
                ]
            ],
        ];

        foreach ($orders as $orderData) {
            // Extract items data
            $items = $orderData['items'];
            unset($orderData['items']);

            // Create order
            $order = Order::create($orderData);

            // Create order items
            foreach ($items as $itemData) {
                $itemData['order_id'] = $order->id;
                OrderItem::create($itemData);
            }

            // Recalculate totals
            $order->calculateTotals();
        }
    }
}