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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company')->nullable();
            $table->string('phone')->nullable();
            $table->enum('status', ['active', 'inactive', 'bounced', 'unsubscribed', 'complained'])->default('active');
            $table->enum('source', ['manual', 'import', 'api', 'newsletter_signup', 'purchase', 'contact_form'])->default('manual');
            $table->string('language', 5)->default('fr');
            $table->string('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['M', 'F', 'O'])->nullable();
            
            // Subscription preferences
            $table->boolean('newsletter_subscribed')->default(false);
            $table->boolean('marketing_subscribed')->default(false);
            $table->boolean('sms_subscribed')->default(false);
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            
            // Engagement tracking
            $table->timestamp('last_email_sent_at')->nullable();
            $table->timestamp('last_email_opened_at')->nullable();
            $table->timestamp('last_email_clicked_at')->nullable();
            $table->unsignedInteger('email_open_count')->default(0);
            $table->unsignedInteger('email_click_count')->default(0);
            
            // User relationship
            $table->unsignedBigInteger('user_id')->nullable(); // Link to registered user
            
            // Custom fields
            $table->json('custom_fields')->nullable();
            $table->json('tags')->nullable(); // Simple tag array
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'newsletter_subscribed']);
            $table->index(['status', 'marketing_subscribed']);
            $table->index('source');
            $table->index('user_id');
            $table->index('country');
            $table->index(['last_email_sent_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};