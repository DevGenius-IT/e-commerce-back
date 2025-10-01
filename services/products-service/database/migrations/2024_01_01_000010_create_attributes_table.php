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
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('value_'); // Valeur de l'attribut
            $table->integer('stock')->default(0); // Stock spécifique à cet attribut
            $table->timestamps();
            
            // Relations vers les autres tables (à ajouter selon les besoins)
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('attribute_group_id')->nullable();
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('attribute_group_id')->references('id')->on('attribute_groups')->onDelete('set null');
            
            $table->index(['product_id', 'attribute_group_id']);
            $table->index('stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};