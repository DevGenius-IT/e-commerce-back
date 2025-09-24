<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->unsignedBigInteger('product_id'); // Foreign key to products in products-service
            $table->integer('quantity');
            $table->decimal('unit_price_ht', 8, 2);
            $table->decimal('unit_price_ttc', 8, 2);
            $table->decimal('total_price_ht', 10, 2);
            $table->decimal('total_price_ttc', 10, 2);
            $table->decimal('vat_rate', 5, 2)->default(0); // TVA rate at time of order
            $table->string('product_name'); // Product name snapshot at time of order
            $table->string('product_ref'); // Product reference snapshot at time of order
            $table->timestamps();
            
            // Indexes for performance
            $table->index('order_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};