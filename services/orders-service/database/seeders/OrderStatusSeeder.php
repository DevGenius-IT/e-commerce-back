<?php

namespace Database\Seeders;

use App\Models\OrderStatus;
use Illuminate\Database\Seeder;

class OrderStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'pending',
                'description' => 'Commande en attente de traitement',
            ],
            [
                'name' => 'confirmed',
                'description' => 'Commande confirmée et en cours de préparation',
            ],
            [
                'name' => 'processing',
                'description' => 'Commande en cours de préparation',
            ],
            [
                'name' => 'shipped',
                'description' => 'Commande expédiée',
            ],
            [
                'name' => 'delivered',
                'description' => 'Commande livrée',
            ],
            [
                'name' => 'cancelled',
                'description' => 'Commande annulée',
            ],
            [
                'name' => 'refunded',
                'description' => 'Commande remboursée',
            ],
            [
                'name' => 'returned',
                'description' => 'Commande retournée',
            ],
        ];

        foreach ($statuses as $status) {
            OrderStatus::firstOrCreate(
                ['name' => $status['name']],
                $status
            );
        }
    }
}