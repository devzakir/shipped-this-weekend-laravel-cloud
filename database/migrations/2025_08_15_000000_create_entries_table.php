<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->string('host')->index();
            $table->string('title')->nullable();
            $table->string('tagline', 80);
            $table->string('author_name');
            $table->string('x_handle')->nullable();
            $table->text('og_image_url')->nullable();
            $table->text('screenshot_url')->nullable();
            $table->unsignedInteger('votes_count')->default(0);
            $table->string('status')->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
