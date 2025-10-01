<?php

namespace Database\Seeders;

use App\Models\Type;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Pourcentage',
                'symbol' => '%'
            ],
            [
                'name' => 'Montant fixe',
                'symbol' => '€'
            ],
            [
                'name' => 'Livraison gratuite',
                'symbol' => '🚚'
            ],
            [
                'name' => 'Première commande',
                'symbol' => '🎁'
            ],
            [
                'name' => 'Fidélité',
                'symbol' => '⭐'
            ]
        ];

        foreach ($types as $type) {
            Type::create($type);
        }
    }
}