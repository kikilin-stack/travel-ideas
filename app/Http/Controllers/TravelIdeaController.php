<?php

namespace App\Http\Controllers;

use App\Models\TravelIdea;
use App\Services\ApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * 旅行想法控制器：CRUD、列表、详情（含 API 数据）
 */
class TravelIdeaController extends Controller
{
    public function __construct(
        protected ApiService $apiService
    ) {}

    public function index(Request $request): View
    {
        $query = TravelIdea::with('user')->public()->orderByDesc('created_at');

        if ($q = $request->get('q')) {
            $query->where(function ($qry) use ($q) {
                $qry->where('destination', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('tags', 'like', "%{$q}%");
            });
        }

        $ideas = $query->paginate(12)->withQueryString();
        return view('travel-ideas.index', compact('ideas'));
    }

    public function show(int $id): View|RedirectResponse
    {
        $idea = TravelIdea::with(['user', 'comments.user'])->findOrFail($id);
        if (!$idea->is_public && $idea->user_id !== Auth::id()) {
            abort(403);
        }

        $weatherData = $this->apiService->getWeather($idea->destination);
        $hotelData = $this->apiService->getHotels($idea->destination, $idea->travel_date?->format('Y-m-d'));
        $foodData = $this->apiService->getFood($idea->destination);

        return view('travel-ideas.show', [
            'idea' => $idea,
            'weatherData' => $weatherData,
            'hotelData' => $hotelData,
            'foodData' => $foodData,
        ]);
    }

    public function create(): View
    {
        return view('travel-ideas.form');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'destination' => 'required|string|max:100',
            'description' => 'required|string',
            'travel_date' => 'nullable|date',
            'tags' => 'nullable|string|max:255',
            'cover_image' => 'nullable|image|max:2048',
            'is_public' => 'nullable|boolean',
        ], [
            'title.required' => '请输入标题',
            'destination.required' => '请输入目的地',
            'description.required' => '请输入描述',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['is_public'] = $request->boolean('is_public', true);

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('covers', 'public');
        }

        $idea = TravelIdea::create($validated);
        return response()->json([
            'success' => true,
            'message' => '发布成功',
            'redirect' => route('travel-ideas.show', $idea->id),
        ]);
    }

    public function edit(int $id): View|RedirectResponse
    {
        $idea = TravelIdea::findOrFail($id);
        if ($idea->user_id !== Auth::id()) {
            abort(403);
        }
        return view('travel-ideas.form', ['idea' => $idea]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $idea = TravelIdea::findOrFail($id);
        if ($idea->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => '无权限'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'destination' => 'required|string|max:100',
            'description' => 'required|string',
            'travel_date' => 'nullable|date',
            'tags' => 'nullable|string|max:255',
            'cover_image' => 'nullable|image|max:2048',
            'is_public' => 'nullable|boolean',
        ]);

        $validated['is_public'] = $request->boolean('is_public', true);

        if ($request->hasFile('cover_image')) {
            if ($idea->cover_image) {
                Storage::disk('public')->delete($idea->cover_image);
            }
            $validated['cover_image'] = $request->file('cover_image')->store('covers', 'public');
        }

        $idea->update($validated);
        return response()->json([
            'success' => true,
            'message' => '保存成功',
            'redirect' => route('travel-ideas.show', $idea->id),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $idea = TravelIdea::findOrFail($id);
        if ($idea->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => '无权限'], 403);
        }
        if ($idea->cover_image) {
            Storage::disk('public')->delete($idea->cover_image);
        }
        $idea->delete();
        return response()->json(['success' => true, 'message' => '已删除']);
    }
}
