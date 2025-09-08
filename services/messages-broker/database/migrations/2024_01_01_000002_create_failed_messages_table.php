<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id');
            $table->string('queue');
            $table->string('exchange')->nullable();
            $table->string('routing_key')->nullable();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->text('exception');
            $table->text('exception_trace');
            $table->integer('failed_attempts')->default(1);
            $table->timestamp('last_failed_at');
            $table->boolean('can_retry')->default(true);
            $table->timestamps();
            
            $table->index(['queue', 'can_retry']);
            $table->index('message_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_messages');
    }
};