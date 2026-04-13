<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 评论控制器：发表评论（AJAX）
 */
class CommentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'travel_idea_id' => 'required|exists:travel_ideas,id',
            'content' => 'required|string|max:255',
        ], [
            'content.required' => '评论内容不能为空',
            'content.max' => '评论不能超过255字',
        ]);

        $comment = Comment::create([
            'travel_idea_id' => $validated['travel_idea_id'],
            'user_id' => Auth::id(),
            'content' => $validated['content'],
        ]);

        $comment->load('user');
        return response()->json([
            'success' => true,
            'comment' => [
                'id' => $comment->id,
                'user_name' => $comment->user->name,
                'content' => $comment->content,
                'created_at' => $comment->created_at->format('Y-m-d H:i'),
            ],
        ]);
    }
}
