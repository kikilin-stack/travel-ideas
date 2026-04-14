<?php

namespace App\Http\Controllers;

use App\Models\TravelIdea;
use App\Services\ApiService;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TravelIdeaController extends Controller
{
    public function __construct(
        protected ApiService $apiService
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $query = TravelIdea::with('user')
            ->withCount('comments')
            ->public()
            ->orderByDesc('created_at');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($qry) use ($like) {
                $qry->where('destination', 'like', $like)
                    ->orWhere('tags', 'like', $like);
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
        $checkInDate = $idea->start_date instanceof Carbon
            ? $idea->start_date->format('Y-m-d')
            : null;
        $hotelData = $this->apiService->getHotels($idea->destination, $checkInDate);
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
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'tags' => 'nullable|string|max:255',
            'cover_image' => 'nullable|image|max:2048',
            'is_public' => 'nullable|boolean',
        ], [
            'title.required' => 'Title is required.',
            'destination.required' => 'Destination is required.',
            'description.required' => 'Description is required.',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['is_public'] = $request->boolean('is_public', true);
        $validated['travel_date'] = $validated['start_date'] ?? null;

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('covers', 'public');
        }

        $idea = TravelIdea::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Travel idea created successfully.',
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
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'destination' => 'required|string|max:100',
            'description' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'tags' => 'nullable|string|max:255',
            'cover_image' => 'nullable|image|max:2048',
            'is_public' => 'nullable|boolean',
        ]);

        $validated['is_public'] = $request->boolean('is_public', true);
        $validated['travel_date'] = $validated['start_date'] ?? null;

        if ($request->hasFile('cover_image')) {
            if ($idea->cover_image) {
                Storage::disk('public')->delete($idea->cover_image);
            }
            $validated['cover_image'] = $request->file('cover_image')->store('covers', 'public');
        }

        $idea->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Travel idea updated successfully.',
            'redirect' => route('travel-ideas.show', $idea->id),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $idea = TravelIdea::findOrFail($id);
        if ($idea->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        if ($idea->cover_image) {
            Storage::disk('public')->delete($idea->cover_image);
        }

        $idea->delete();

        return response()->json(['success' => true, 'message' => 'Travel idea deleted.']);
    }
}
