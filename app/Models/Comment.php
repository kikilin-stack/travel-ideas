<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 评论模型：用户对旅行想法的评论
 */
class Comment extends Model
{
    protected $fillable = [
        'travel_idea_id',
        'user_id',
        'content',
    ];

    /** 评论用户 */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 所属旅行想法 */
    public function travelIdea(): BelongsTo
    {
        return $this->belongsTo(TravelIdea::class);
    }
}
