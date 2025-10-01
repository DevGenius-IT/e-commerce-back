<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('original_url');
            $table->string('thumbnail_url')->nullable();
            $table->string('medium_url')->nullable();
            $table->string('filename');
            $table->enum('type', ['main', 'gallery', 'thumbnail'])->default('gallery');
            $table->string('alt_text')->nullable();
            $table->integer('position')->default(0);
            $table->bigInteger('size'); // Size in bytes
            $table->string('mime_type');
            $table->timestamps();

            $table->index(['product_id', 'type']);
            $table->index(['product_id', 'position']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_images');
    }
};
