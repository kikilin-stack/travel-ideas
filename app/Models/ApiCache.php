<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * API 缓存模型：缓存外部 API 响应数据
 */
class ApiCache extends Model
{
    protected $table = 'api_cache';

    protected $fillable = [
        'cache_key',
        'api_type',
        'data',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /** 只查未过期的缓存 */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /** 获取有效缓存 */
    public static function getCache(string $key): ?self
    {
        return static::valid()->where('cache_key', $key)->first();
    }

    /** 设置缓存 */
    public static function setCache(string $key, string $type, array $data, int $minutes): void
    {
        static::updateOrCreate(
            ['cache_key' => $key],
            [
                'api_type' => $type,
                'data' => $data,
                'expires_at' => now()->addMinutes($minutes),
            ]
        );
    }
}
