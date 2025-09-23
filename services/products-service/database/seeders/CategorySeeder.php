<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Electronic devices and accessories',
                'children' => [
                    [
                        'name' => 'Smartphones',
                        'slug' => 'smartphones',
                        'description' => 'Mobile phones and accessories'
                    ],
                    [
                        'name' => 'Laptops',
                        'slug' => 'laptops',
                        'description' => 'Laptop computers and accessories'
                    ],
                    [
                        'name' => 'Headphones',
                        'slug' => 'headphones',
                        'description' => 'Audio headphones and earbuds'
                    ]
                ]
            ],
            [
                'name' => 'Clothing',
                'slug' => 'clothing',
                'description' => 'Fashion and apparel',
                'children' => [
                    [
                        'name' => 'Men\'s Clothing',
                        'slug' => 'mens-clothing',
                        'description' => 'Men\'s fashion and apparel',
                        'children' => [
                            ['name' => 'T-Shirts', 'slug' => 'mens-t-shirts'],
                            ['name' => 'Jeans', 'slug' => 'mens-jeans'],
                            ['name' => 'Jackets', 'slug' => 'mens-jackets']
                        ]
                    ],
                    [
                        'name' => 'Women\'s Clothing',
                        'slug' => 'womens-clothing',
                        'description' => 'Women\'s fashion and apparel',
                        'children' => [
                            ['name' => 'Dresses', 'slug' => 'womens-dresses'],
                            ['name' => 'Tops', 'slug' => 'womens-tops'],
                            ['name' => 'Pants', 'slug' => 'womens-pants']
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Home & Garden',
                'slug' => 'home-garden',
                'description' => 'Home improvement and garden supplies',
                'children' => [
                    [
                        'name' => 'Furniture',
                        'slug' => 'furniture',
                        'description' => 'Indoor and outdoor furniture'
                    ],
                    [
                        'name' => 'Garden Tools',
                        'slug' => 'garden-tools',
                        'description' => 'Gardening equipment and tools'
                    ]
                ]
            ],
            [
                'name' => 'Sports & Outdoors',
                'slug' => 'sports-outdoors',
                'description' => 'Sports equipment and outdoor gear',
                'children' => [
                    [
                        'name' => 'Fitness Equipment',
                        'slug' => 'fitness-equipment',
                        'description' => 'Exercise and fitness gear'
                    ],
                    [
                        'name' => 'Outdoor Gear',
                        'slug' => 'outdoor-gear',
                        'description' => 'Camping and hiking equipment'
                    ]
                ]
            ]
        ];

        foreach ($categories as $index => $categoryData) {
            $this->createCategoryWithChildren($categoryData, null, ($index + 1) * 10);
        }
    }

    private function createCategoryWithChildren(array $categoryData, ?int $parentId = null, int $sortOrder = 0): Category
    {
        $children = $categoryData['children'] ?? [];
        unset($categoryData['children']);

        $category = Category::firstOrCreate([
            'slug' => $categoryData['slug'],
            'parent_id' => $parentId
        ], [
            ...$categoryData,
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
            'is_active' => true
        ]);

        foreach ($children as $index => $childData) {
            $this->createCategoryWithChildren($childData, $category->id, ($index + 1) * 10);
        }

        return $category;
    }
}