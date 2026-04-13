<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 旅行想法表：存储用户发布的旅行计划
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('destination', 100);
            $table->string('title', 200);
            $table->text('description');
            $table->date('travel_date')->nullable();
            $table->string('tags', 255)->nullable();
            $table->string('cover_image', 255)->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_ideas');
    }
};
