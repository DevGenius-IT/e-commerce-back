<?php

namespace Database\Seeders;

use App\Models\AttributeGroup;
use Illuminate\Database\Seeder;

class AttributeGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attributeGroups = [
            ['name' => 'Color'],
            ['name' => 'Size'],
            ['name' => 'Material'],
            ['name' => 'Storage Capacity'],
            ['name' => 'Memory'],
            ['name' => 'Processor'],
            ['name' => 'Screen Size'],
            ['name' => 'Resolution'],
            ['name' => 'Connectivity'],
            ['name' => 'Weight'],
        ];

        foreach ($attributeGroups as $group) {
            AttributeGroup::firstOrCreate(['name' => $group['name']], $group);
        }
    }
}