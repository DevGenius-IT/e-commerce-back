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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Template name
            $table->string('slug')->unique(); // Unique identifier for templates
            $table->string('subject');
            $table->longText('html_content'); // HTML version
            $table->text('plain_content')->nullable(); // Plain text version
            $table->json('variables')->nullable(); // Available variables for this template
            $table->string('category')->default('newsletter'); // newsletter, transactional, promotional
            $table->boolean('is_active')->default(true);
            $table->integer('created_by')->nullable(); // User ID who created the template
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['slug']);
            $table->index(['category']);
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};