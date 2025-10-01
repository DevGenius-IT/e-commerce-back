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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->decimal('total_amount_ht', 10, 2)->default(0);
            $table->decimal('total_amount_ttc', 10, 2)->default(0);
            $table->decimal('total_discount', 10, 2)->default(0);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys to other services
            $table->unsignedBigInteger('user_id'); // Foreign key to users in auth-service
            $table->unsignedBigInteger('billing_address_id'); // Foreign key to addresses in addresses-service
            $table->unsignedBigInteger('shipping_address_id')->nullable(); // Foreign key to addresses in addresses-service
            $table->foreignId('status_id')->constrained('order_status');
            
            // Indexes for performance
            $table->index('user_id');
            $table->index('billing_address_id');
            $table->index('shipping_address_id');
            $table->index('status_id');
            $table->index('order_number');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};