<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\TravelIdeaController;
use App\Services\ApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('travel-ideas.index'))->name('home');

Route::redirect('/login/', '/login', 301);
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/travel-ideas', [TravelIdeaController::class, 'index'])->name('travel-ideas.index');

Route::middleware('auth')->group(function () {
    Route::get('/travel-ideas/create', [TravelIdeaController::class, 'create'])->name('travel-ideas.create');
    Route::post('/travel-ideas', [TravelIdeaController::class, 'store'])->name('travel-ideas.store');
    Route::get('/travel-ideas/{id}/edit', [TravelIdeaController::class, 'edit'])->name('travel-ideas.edit')->whereNumber('id');
    Route::put('/travel-ideas/{id}', [TravelIdeaController::class, 'update'])->name('travel-ideas.update')->whereNumber('id');
    Route::delete('/travel-ideas/{id}', [TravelIdeaController::class, 'destroy'])->name('travel-ideas.destroy')->whereNumber('id');
    Route::post('/comments', [CommentController::class, 'store'])->name('comments.store');
});

Route::get('/travel-ideas/{id}/comments', [CommentController::class, 'index'])->name('comments.index')->whereNumber('id');
Route::get('/travel-ideas/{id}', [TravelIdeaController::class, 'show'])->name('travel-ideas.show')->whereNumber('id');

Route::get('/debug/weather', function (ApiService $apiService) {
    $city = request('city', 'Tokyo');
    $key = (string) config('services.openweather.key');
    $queryCity = match ($city) {
        '东京' => 'Tokyo',
        '巴黎' => 'Paris',
        '纽约' => 'New York',
        '北京' => 'Beijing',
        '上海' => 'Shanghai',
        default => $city,
    };

    $result = [
        'city' => $city,
        'query_city' => $queryCity,
        'key_prefix' => $key ? substr($key, 0, 6) . '***' : '(empty)',
        'shell_exec_available' => function_exists('shell_exec'),
    ];

    try {
        $res = Http::timeout(12)->get('https://api.openweathermap.org/data/2.5/forecast', [
            'q' => $queryCity,
            'appid' => $key,
            'units' => 'metric',
            'lang' => 'en',
            'cnt' => 5,
        ]);
        $result['laravel_http'] = [
            'status' => $res->status(),
            'ok' => $res->successful(),
            'body' => mb_substr($res->body(), 0, 300),
        ];
    } catch (\Throwable $e) {
        $result['laravel_http'] = ['error' => $e->getMessage()];
    }

    try {
        if (function_exists('shell_exec')) {
            $url = 'https://api.openweathermap.org/data/2.5/forecast?q=' . urlencode($queryCity)
                . '&appid=' . urlencode($key)
                . '&units=metric&lang=en&cnt=5';
            $raw = shell_exec('/usr/bin/curl -sS --max-time 15 ' . escapeshellarg($url));
            $result['shell_curl'] = [
                'has_output' => is_string($raw) && trim($raw) !== '',
                'body' => is_string($raw) ? mb_substr($raw, 0, 300) : null,
            ];
        } else {
            $result['shell_curl'] = ['error' => 'shell_exec disabled'];
        }
    } catch (\Throwable $e) {
        $result['shell_curl'] = ['error' => $e->getMessage()];
    }

    $weather = $apiService->getWeather($city);
    $result['app_result_source'] = is_array($weather) ? ($weather['source'] ?? 'unknown') : 'false';
    $result['app_result_preview'] = is_array($weather) ? array_slice($weather['list'] ?? [], 0, 1) : null;

    return response()->json($result);
});
