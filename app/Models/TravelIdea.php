<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 旅行想法模型：用户发布的旅行计划
 */
class TravelIdea extends Model
{
    protected $fillable = [
        'user_id',
        'destination',
        'title',
        'description',
        'start_date',
        'end_date',
        'travel_date',
        'tags',
        'cover_image',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'travel_date' => 'date',
            'is_public' => 'boolean',
        ];
    }

    /** 所属用户 */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 评论列表（按创建时间倒序） */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderByDesc('created_at');
    }

    /** 只查询公开的想法 */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /** 按目的地筛选 */
    public function scopeByDestination($query, string $destination)
    {
        return $query->where('destination', 'like', '%' . $destination . '%');
    }

    /** 将 tags 字符串转为数组 */
    public function getTagArrayAttribute(): array
    {
        if (empty($this->tags)) {
            return [];
        }
        return array_map('trim', explode(',', $this->tags));
    }
}
