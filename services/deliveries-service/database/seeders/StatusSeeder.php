<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'pending',
                'color' => '#6B7280',
                'description' => 'Delivery is pending and waiting to be processed',
            ],
            [
                'name' => 'processing',
                'color' => '#F59E0B',
                'description' => 'Delivery is being prepared at the sale point',
            ],
            [
                'name' => 'ready_for_pickup',
                'color' => '#10B981',
                'description' => 'Package is ready for carrier pickup',
            ],
            [
                'name' => 'picked_up',
                'color' => '#3B82F6',
                'description' => 'Package has been picked up by the carrier',
            ],
            [
                'name' => 'in_transit',
                'color' => '#8B5CF6',
                'description' => 'Package is in transit to destination',
            ],
            [
                'name' => 'out_for_delivery',
                'color' => '#06B6D4',
                'description' => 'Package is out for final delivery',
            ],
            [
                'name' => 'delivered',
                'color' => '#059669',
                'description' => 'Package has been successfully delivered',
            ],
            [
                'name' => 'delivery_failed',
                'color' => '#DC2626',
                'description' => 'Delivery attempt failed',
            ],
            [
                'name' => 'returned',
                'color' => '#7C2D12',
                'description' => 'Package has been returned to sender',
            ],
            [
                'name' => 'cancelled',
                'color' => '#374151',
                'description' => 'Delivery has been cancelled',
            ],
        ];

        foreach ($statuses as $status) {
            Status::create($status);
        }
    }
}