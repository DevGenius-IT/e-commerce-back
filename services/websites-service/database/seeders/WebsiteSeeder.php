<?php

namespace Database\Seeders;

use App\Models\Website;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WebsiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $websites = [
            [
                'name' => 'E-commerce Principal',
                'domain' => 'shop.example.com',
            ],
            [
                'name' => 'Site Corporate',
                'domain' => 'www.example.com',
            ],
            [
                'name' => 'Blog E-commerce',
                'domain' => 'blog.example.com',
            ],
            [
                'name' => 'API Documentation',
                'domain' => 'api.example.com',
            ],
        ];

        foreach ($websites as $website) {
            Website::create($website);
        }
    }
}