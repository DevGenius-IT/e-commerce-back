<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use App\Models\Type;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PromoCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = Type::all();
        $percentageType = $types->where('name', 'Pourcentage')->first();
        $fixedType = $types->where('name', 'Montant fixe')->first();
        $shippingType = $types->where('name', 'Livraison gratuite')->first();
        $firstOrderType = $types->where('name', 'Première commande')->first();
        $loyaltyType = $types->where('name', 'Fidélité')->first();

        $promoCodes = [
            // Pourcentage discounts
            [
                'name' => 'Bienvenue 10%',
                'code' => 'WELCOME10',
                'discount' => 10.00,
                'id_1' => $percentageType?->id
            ],
            [
                'name' => 'Black Friday 25%',
                'code' => 'BLACKFRIDAY25',
                'discount' => 25.00,
                'id_1' => $percentageType?->id
            ],
            [
                'name' => 'Été 15%',
                'code' => 'SUMMER15',
                'discount' => 15.00,
                'id_1' => $percentageType?->id
            ],
            
            // Montants fixes
            [
                'name' => 'Réduction 5€',
                'code' => 'SAVE5',
                'discount' => 5.00,
                'id_1' => $fixedType?->id
            ],
            [
                'name' => 'Réduction 20€',
                'code' => 'BIG20',
                'discount' => 20.00,
                'id_1' => $fixedType?->id
            ],
            [
                'name' => 'Cadeau 50€',
                'code' => 'GIFT50',
                'discount' => 50.00,
                'id_1' => $fixedType?->id
            ],
            
            // Livraison gratuite
            [
                'name' => 'Livraison offerte',
                'code' => 'FREESHIP',
                'discount' => 7.99,
                'id_1' => $shippingType?->id
            ],
            [
                'name' => 'Port gratuit',
                'code' => 'NOSHIP',
                'discount' => 9.99,
                'id_1' => $shippingType?->id
            ],
            
            // Première commande
            [
                'name' => 'Premier achat 20%',
                'code' => 'FIRST20',
                'discount' => 20.00,
                'id_1' => $firstOrderType?->id
            ],
            [
                'name' => 'Nouveau client 15€',
                'code' => 'NEWCLIENT15',
                'discount' => 15.00,
                'id_1' => $firstOrderType?->id
            ],
            
            // Fidélité
            [
                'name' => 'Client VIP 30%',
                'code' => 'VIP30',
                'discount' => 30.00,
                'id_1' => $loyaltyType?->id
            ],
            [
                'name' => 'Fidèle 12%',
                'code' => 'LOYAL12',
                'discount' => 12.00,
                'id_1' => $loyaltyType?->id
            ]
        ];

        foreach ($promoCodes as $promoCode) {
            PromoCode::create($promoCode);
        }
    }
}