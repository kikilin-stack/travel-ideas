<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\TravelIdea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    /**
     * Poll for new comments on a travel idea (return rows with id greater than after_id, ascending id).
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $idea = TravelIdea::findOrFail($id);
        if (! $idea->is_public && $idea->user_id !== Auth::id()) {
            abort(403);
        }

        $afterId = max(0, (int) $request->query('after_id', 0));

        $comments = Comment::query()
            ->where('travel_idea_id', $idea->id)
            ->where('id', '>', $afterId)
            ->with('user')
            ->orderBy('id')
            ->get();

        return response()->json([
            'comments' => $comments->map(fn ($c) => [
                'id' => $c->id,
                'user_name' => $c->user->name,
                'content' => $c->content,
                'created_at' => $c->created_at->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'travel_idea_id' => 'required|exists:travel_ideas,id',
            'content' => 'required|string|max:255',
        ], [
            'content.required' => 'Comment content is required.',
            'content.max' => 'Comment must be 255 characters or less.',
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
