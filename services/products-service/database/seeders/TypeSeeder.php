<?php

namespace Database\Seeders;

use App\Models\Type;
use Illuminate\Database\Seeder;

class TypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Smartphone'],
            ['name' => 'Laptop'],
            ['name' => 'Tablet'],
            ['name' => 'Desktop'],
            ['name' => 'Monitor'],
            ['name' => 'Keyboard'],
            ['name' => 'Mouse'],
            ['name' => 'Headphones'],
            ['name' => 'Speaker'],
            ['name' => 'Camera'],
        ];

        foreach ($types as $type) {
            Type::firstOrCreate(['name' => $type['name']], $type);
        }
    }
}