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
        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['marketing', 'newsletter', 'segmentation', 'custom'])->default('custom');
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->json('criteria')->nullable(); // For dynamic lists based on filters
            $table->boolean('is_dynamic')->default(false); // Static or dynamic list
            $table->unsignedInteger('contact_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->json('metadata')->nullable(); // Additional custom data
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'type']);
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_lists');
    }
};