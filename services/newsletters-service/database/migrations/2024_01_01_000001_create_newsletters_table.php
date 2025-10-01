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
        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique()->index();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->enum('status', ['subscribed', 'unsubscribed', 'pending', 'bounced'])->default('pending');
            $table->json('preferences')->nullable(); // Store user preferences as JSON
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('subscription_source')->nullable(); // website, checkout, api, etc.
            $table->string('unsubscribe_token')->unique()->nullable(); // For one-click unsubscribe
            $table->integer('bounce_count')->default(0);
            $table->timestamp('last_bounce_at')->nullable();
            $table->text('notes')->nullable(); // Admin notes
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['status']);
            $table->index(['subscribed_at']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletters');
    }
};