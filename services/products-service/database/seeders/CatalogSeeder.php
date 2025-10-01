<?php

namespace Database\Seeders;

use App\Models\Catalog;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $catalogs = [
            ['name' => 'Main Catalog'],
            ['name' => 'Premium Products'],
            ['name' => 'Budget Collection'],
            ['name' => 'New Arrivals'],
            ['name' => 'Clearance'],
            ['name' => 'Professional'],
            ['name' => 'Consumer'],
            ['name' => 'Enterprise'],
        ];

        foreach ($catalogs as $catalog) {
            Catalog::firstOrCreate(['name' => $catalog['name']], $catalog);
        }
    }
}