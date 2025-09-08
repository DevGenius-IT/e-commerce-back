<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->string('queue');
            $table->string('exchange')->nullable();
            $table->string('routing_key')->nullable();
            $table->string('type');
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->enum('status', ['pending', 'published', 'consumed', 'failed', 'dead_letter']);
            $table->integer('retry_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('consumer_tag')->nullable();
            $table->timestamps();
            
            $table->index(['queue', 'status']);
            $table->index('message_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};