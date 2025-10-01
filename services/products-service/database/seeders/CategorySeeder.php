<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Electronics'],
            ['name' => 'Computers'],
            ['name' => 'Mobile Devices'],
            ['name' => 'Audio & Video'],
            ['name' => 'Gaming'],
            ['name' => 'Accessories'],
            ['name' => 'Networking'],
            ['name' => 'Storage'],
            ['name' => 'Monitors & Displays'],
            ['name' => 'Input Devices'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category['name']], $category);
        }
    }
}