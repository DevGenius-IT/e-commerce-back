<?php

namespace Database\Seeders;

use App\Models\CharacteristicGroup;
use Illuminate\Database\Seeder;

class CharacteristicGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $characteristicGroups = [
            ['name' => 'Technical Specifications'],
            ['name' => 'Physical Dimensions'],
            ['name' => 'Performance'],
            ['name' => 'Compatibility'],
            ['name' => 'Features'],
            ['name' => 'Power'],
            ['name' => 'Warranty'],
            ['name' => 'Certification'],
        ];

        foreach ($characteristicGroups as $group) {
            CharacteristicGroup::firstOrCreate(['name' => $group['name']], $group);
        }
    }
}