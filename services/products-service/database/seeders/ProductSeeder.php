<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Type;
use App\Models\Category;
use App\Models\Catalog;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some brands, types, categories, and catalogs
        $appleBrand = Brand::where('name', 'Apple')->first();
        $samsungBrand = Brand::where('name', 'Samsung')->first();
        $smartphoneType = Type::where('name', 'Smartphone')->first();
        $laptopType = Type::where('name', 'Laptop')->first();
        $electronicsCategory = Category::where('name', 'Electronics')->first();
        $mobileCategory = Category::where('name', 'Mobile Devices')->first();
        $mainCatalog = Catalog::where('name', 'Main Catalog')->first();
        $premiumCatalog = Catalog::where('name', 'Premium Products')->first();

        $products = [
            [
                'name' => 'iPhone 15 Pro',
                'ref' => 'IPHONE15PRO-128',
                'price_ht' => 999.00,
                'stock' => 50,
                'id_1' => $appleBrand?->id,
                'types' => [$smartphoneType?->id],
                'categories' => [$electronicsCategory?->id, $mobileCategory?->id],
                'catalogs' => [$mainCatalog?->id, $premiumCatalog?->id],
            ],
            [
                'name' => 'Samsung Galaxy S24',
                'ref' => 'GALAXY-S24-256',
                'price_ht' => 849.00,
                'stock' => 30,
                'id_1' => $samsungBrand?->id,
                'types' => [$smartphoneType?->id],
                'categories' => [$electronicsCategory?->id, $mobileCategory?->id],
                'catalogs' => [$mainCatalog?->id],
            ],
            [
                'name' => 'MacBook Pro 14"',
                'ref' => 'MBP14-M3-512',
                'price_ht' => 1999.00,
                'stock' => 15,
                'id_1' => $appleBrand?->id,
                'types' => [$laptopType?->id],
                'categories' => [$electronicsCategory?->id],
                'catalogs' => [$mainCatalog?->id, $premiumCatalog?->id],
            ],
        ];

        foreach ($products as $productData) {
            $types = $productData['types'] ?? [];
            $categories = $productData['categories'] ?? [];
            $catalogs = $productData['catalogs'] ?? [];
            
            unset($productData['types'], $productData['categories'], $productData['catalogs']);

            $product = Product::firstOrCreate(
                ['ref' => $productData['ref']], 
                $productData
            );

            // Attach relationships
            if (!empty($types)) {
                $product->types()->sync(array_filter($types));
            }
            if (!empty($categories)) {
                $product->categories()->sync(array_filter($categories));
            }
            if (!empty($catalogs)) {
                $product->catalogs()->sync(array_filter($catalogs));
            }
        }
    }
}