<?php

namespace Database\Seeders;

use App\Models\Basket;
use App\Models\BasketItem;
use App\Models\PromoCode;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BasketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Simulate baskets for existing users (assuming user IDs 1-5 exist)
        for ($userId = 1; $userId <= 5; $userId++) {
            
            $basket = Basket::create([
                'user_id' => $userId,
                'amount' => 0
            ]);

            // Add random items to each basket
            $numberOfItems = rand(1, 4);
            
            for ($i = 0; $i < $numberOfItems; $i++) {
                BasketItem::create([
                    'basket_id' => $basket->id,
                    'product_id' => rand(1, 20), // Assuming product IDs 1-20 exist
                    'quantity' => rand(1, 3),
                    'price_ht' => round(rand(999, 9999) / 100, 2) // Random price between 9.99 and 99.99
                ]);
            }

            // Sometimes apply a random promo code
            if (rand(1, 3) === 1) { // 33% chance
                $promoCodes = PromoCode::all();
                if ($promoCodes->count() > 0) {
                    $randomPromoCode = $promoCodes->random();
                    $basket->promoCodes()->attach($randomPromoCode->id);
                }
            }

            // Calculate final total
            $basket->calculateTotal();
        }

        // Create some empty baskets
        for ($userId = 6; $userId <= 8; $userId++) {
            Basket::create([
                'user_id' => $userId,
                'amount' => 0
            ]);
        }
    }
}