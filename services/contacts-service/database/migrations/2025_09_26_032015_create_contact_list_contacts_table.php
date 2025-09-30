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
        Schema::create('contact_list_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_list_id');
            $table->unsignedBigInteger('contact_id');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('added_at')->useCurrent();
            $table->timestamp('removed_at')->nullable();
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('contact_list_id')->references('id')->on('contact_lists')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['contact_list_id', 'contact_id'], 'unique_list_contact');
            
            // Indexes
            $table->index(['contact_list_id', 'status']);
            $table->index(['contact_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_list_contacts');
    }
};