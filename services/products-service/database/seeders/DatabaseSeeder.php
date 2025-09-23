<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            VatSeeder::class,
            BrandSeeder::class,
            TypeSeeder::class,
            CategorySeeder::class,
            CatalogSeeder::class,
            AttributeGroupSeeder::class,
            CharacteristicGroupSeeder::class,
            ProductSeeder::class,
        ]);
    }
}