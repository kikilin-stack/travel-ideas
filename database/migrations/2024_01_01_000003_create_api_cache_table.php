<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API 缓存表：缓存天气、酒店、美食等外部 API 响应，减少重复请求
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 255)->unique();
            $table->string('api_type', 50);
            $table->json('data');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_cache');
    }
};
