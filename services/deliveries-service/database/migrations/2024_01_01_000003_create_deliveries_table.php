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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->unsignedBigInteger('order_id'); // Reference to orders-service
            $table->foreignId('sale_point_id')->constrained('sale_points')->onDelete('cascade');
            $table->foreignId('status_id')->constrained('status')->onDelete('restrict');
            
            // Delivery information
            $table->string('delivery_method')->default('standard'); // standard, express, pickup
            $table->decimal('shipping_cost', 10, 2)->default(0.00);
            $table->text('delivery_address')->nullable();
            $table->text('special_instructions')->nullable();
            
            // Timing
            $table->timestamp('estimated_delivery_date')->nullable();
            $table->timestamp('actual_delivery_date')->nullable();
            $table->timestamp('shipped_at')->nullable();
            
            // Carrier information
            $table->string('carrier_name')->nullable();
            $table->string('carrier_tracking_number')->nullable();
            $table->text('carrier_details')->nullable(); // JSON for additional carrier info
            
            // Recipient information
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->text('delivery_notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Add indexes for performance
            $table->index('order_id');
            $table->index(['status_id', 'deleted_at']);
            $table->index('tracking_number');
            $table->index(['carrier_name', 'carrier_tracking_number']);
            $table->index(['estimated_delivery_date', 'actual_delivery_date']);
            $table->index(['delivery_method', 'shipped_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};