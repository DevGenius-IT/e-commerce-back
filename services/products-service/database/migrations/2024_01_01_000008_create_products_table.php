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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ref')->unique(); // Référence produit
            $table->decimal('price_ht', 10, 2); // Prix hors taxe
            $table->integer('stock')->default(0);
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('id_1')->nullable(); // FK vers brand
            
            // Relations
            $table->foreign('id_1')->references('id')->on('brands')->onDelete('set null');
            
            // Indexes
            $table->index(['ref', 'deleted_at']);
            $table->index(['name', 'deleted_at']);
            $table->index('stock');
            $table->index('price_ht');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};