<?php

namespace Database\Seeders;

use App\Models\Vat;
use Illuminate\Database\Seeder;

class VatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vatRates = [
            ['name' => 'TVA 0%', 'value_' => 0.00],
            ['name' => 'TVA 5.5%', 'value_' => 5.5],
            ['name' => 'TVA 10%', 'value_' => 10.0],
            ['name' => 'TVA 20%', 'value_' => 20.0],
        ];

        foreach ($vatRates as $vat) {
            Vat::firstOrCreate(['name' => $vat['name']], $vat);
        }
    }
}