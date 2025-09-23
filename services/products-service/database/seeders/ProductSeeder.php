<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'iPhone 15 Pro',
                'sku' => 'IPHONE-15-PRO-256GB',
                'description' => 'The latest iPhone with A17 Pro chip, titanium design, and advanced camera system.',
                'short_description' => 'Latest iPhone with titanium design and A17 Pro chip.',
                'price' => 1199.00,
                'compare_price' => 1299.00,
                'cost' => 800.00,
                'quantity' => 50,
                'weight' => 0.187,
                'is_featured' => true,
                'categories' => ['smartphones'],
                'images' => [
                    ['url' => 'products/iphone-15-pro-1.jpg', 'alt_text' => 'iPhone 15 Pro front view', 'is_primary' => true],
                    ['url' => 'products/iphone-15-pro-2.jpg', 'alt_text' => 'iPhone 15 Pro back view'],
                    ['url' => 'products/iphone-15-pro-3.jpg', 'alt_text' => 'iPhone 15 Pro side view']
                ],
                'tags' => ['apple', 'smartphone', 'premium', 'titanium']
            ],
            [
                'name' => 'MacBook Pro 16-inch',
                'sku' => 'MACBOOK-PRO-16-M3',
                'description' => 'Powerful laptop with M3 chip, 16-inch Liquid Retina XDR display, and up to 18 hours of battery life.',
                'short_description' => 'Professional laptop with M3 chip and stunning display.',
                'price' => 2499.00,
                'cost' => 1800.00,
                'quantity' => 25,
                'weight' => 2.16,
                'dimensions' => ['length' => 35.57, 'width' => 24.81, 'height' => 1.68],
                'is_featured' => true,
                'categories' => ['laptops'],
                'images' => [
                    ['url' => 'products/macbook-pro-16-1.jpg', 'alt_text' => 'MacBook Pro 16-inch open', 'is_primary' => true],
                    ['url' => 'products/macbook-pro-16-2.jpg', 'alt_text' => 'MacBook Pro 16-inch closed']
                ],
                'tags' => ['apple', 'laptop', 'professional', 'M3']
            ],
            [
                'name' => 'AirPods Pro (2nd generation)',
                'sku' => 'AIRPODS-PRO-2ND-GEN',
                'description' => 'Active noise cancellation, transparency mode, spatial audio, and up to 6 hours of listening time.',
                'short_description' => 'Premium wireless earbuds with active noise cancellation.',
                'price' => 249.00,
                'cost' => 120.00,
                'quantity' => 100,
                'weight' => 0.056,
                'categories' => ['headphones'],
                'images' => [
                    ['url' => 'products/airpods-pro-1.jpg', 'alt_text' => 'AirPods Pro with case', 'is_primary' => true],
                    ['url' => 'products/airpods-pro-2.jpg', 'alt_text' => 'AirPods Pro in ear']
                ],
                'tags' => ['apple', 'wireless', 'earbuds', 'noise-cancellation']
            ],
            [
                'name' => 'Men\'s Cotton T-Shirt',
                'sku' => 'MENS-COTTON-TEE-L-BLUE',
                'description' => '100% organic cotton t-shirt, comfortable fit, available in multiple colors.',
                'short_description' => 'Comfortable organic cotton t-shirt for everyday wear.',
                'price' => 29.99,
                'cost' => 12.00,
                'quantity' => 200,
                'weight' => 0.2,
                'categories' => ['mens-t-shirts'],
                'images' => [
                    ['url' => 'products/mens-tshirt-blue-1.jpg', 'alt_text' => 'Blue cotton t-shirt front', 'is_primary' => true],
                    ['url' => 'products/mens-tshirt-blue-2.jpg', 'alt_text' => 'Blue cotton t-shirt back']
                ],
                'tags' => ['cotton', 'casual', 'mens', 'basic']
            ],
            [
                'name' => 'Women\'s Summer Dress',
                'sku' => 'WOMENS-SUMMER-DRESS-M-RED',
                'description' => 'Elegant summer dress perfect for casual and semi-formal occasions.',
                'short_description' => 'Elegant and comfortable summer dress.',
                'price' => 89.99,
                'compare_price' => 119.99,
                'cost' => 35.00,
                'quantity' => 75,
                'weight' => 0.3,
                'is_featured' => true,
                'categories' => ['womens-dresses'],
                'images' => [
                    ['url' => 'products/womens-dress-red-1.jpg', 'alt_text' => 'Red summer dress front', 'is_primary' => true],
                    ['url' => 'products/womens-dress-red-2.jpg', 'alt_text' => 'Red summer dress side view']
                ],
                'tags' => ['dress', 'summer', 'elegant', 'womens']
            ],
            [
                'name' => 'Ergonomic Office Chair',
                'sku' => 'OFFICE-CHAIR-ERG-BLACK',
                'description' => 'Ergonomic office chair with lumbar support, adjustable height, and breathable mesh back.',
                'short_description' => 'Comfortable ergonomic office chair with lumbar support.',
                'price' => 299.99,
                'cost' => 150.00,
                'quantity' => 30,
                'weight' => 18.5,
                'dimensions' => ['length' => 66, 'width' => 66, 'height' => 120],
                'requires_shipping' => true,
                'categories' => ['furniture'],
                'images' => [
                    ['url' => 'products/office-chair-1.jpg', 'alt_text' => 'Black ergonomic office chair', 'is_primary' => true],
                    ['url' => 'products/office-chair-2.jpg', 'alt_text' => 'Office chair side view']
                ],
                'tags' => ['office', 'chair', 'ergonomic', 'furniture']
            ]
        ];

        foreach ($products as $productData) {
            $categories = $productData['categories'];
            $images = $productData['images'];
            unset($productData['categories'], $productData['images']);

            // Create product
            $productSlug = Str::slug($productData['name']);
            $product = Product::firstOrCreate(
                ['sku' => $productData['sku']], 
                [
                    ...$productData,
                    'slug' => $productSlug,
                    'is_active' => true
                ]
            );

            // Attach categories
            foreach ($categories as $categorySlug) {
                $category = Category::where('slug', $categorySlug)->first();
                if ($category && !$product->categories()->where('category_id', $category->id)->exists()) {
                    $product->categories()->attach($category->id);
                }
            }

            // Create images
            foreach ($images as $index => $imageData) {
                ProductImage::firstOrCreate([
                    'product_id' => $product->id,
                    'url' => $imageData['url']
                ], [
                    ...$imageData,
                    'product_id' => $product->id,
                    'sort_order' => $index + 1
                ]);
            }
        }
    }
}