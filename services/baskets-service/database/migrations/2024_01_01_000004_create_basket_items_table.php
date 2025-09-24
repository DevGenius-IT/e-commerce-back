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
        Schema::create('basket_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('basket_id');
            $table->unsignedBigInteger('product_id'); // Foreign key to products in products-service
            $table->integer('quantity')->default(1);
            $table->decimal('price_ht', 8, 2);
            $table->timestamps();
            
            $table->foreign('basket_id')->references('id')->on('baskets')->onDelete('cascade');
            $table->index(['basket_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('basket_items');
    }
};