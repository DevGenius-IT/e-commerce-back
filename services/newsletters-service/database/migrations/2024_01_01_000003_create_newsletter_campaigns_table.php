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
        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'sent', 'delivered', 'opened', 'clicked', 'bounced', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('bounce_reason')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('click_data')->nullable(); // Track which links were clicked
            $table->string('user_agent')->nullable(); // For open tracking
            $table->string('ip_address')->nullable(); // For analytics
            $table->integer('open_count')->default(0); // Track multiple opens
            $table->integer('click_count')->default(0); // Track multiple clicks
            $table->timestamps();

            // Indexes for better performance
            $table->index(['newsletter_id', 'campaign_id']);
            $table->index(['status']);
            $table->index(['sent_at']);
            $table->index(['opened_at']);
            $table->index(['clicked_at']);

            // Unique constraint to prevent duplicate campaign sends to same newsletter
            $table->unique(['newsletter_id', 'campaign_id'], 'unique_newsletter_campaign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
    }
};