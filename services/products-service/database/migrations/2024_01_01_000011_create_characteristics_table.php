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
        Schema::create('characteristics', function (Blueprint $table) {
            $table->id();
            $table->string('value_'); // Valeur de la caractéristique
            $table->timestamps();
            
            // Relations
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('related_characteristic_id')->nullable();
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('related_characteristic_id')->references('id')->on('related_characteristics')->onDelete('set null');
            
            $table->index(['product_id', 'related_characteristic_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('characteristics');
    }
};