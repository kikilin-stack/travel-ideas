<?php

namespace App\Services;

use App\Models\ApiCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 统一处理外部 API 调用：天气、酒店、美食
 */
class ApiService
{
    protected ?string $weatherApiKey;
    protected ?string $amadeusClientId;
    protected ?string $amadeusClientSecret;
    protected ?string $spoonacularApiKey;

    /** 城市名 -> IATA 代码 */
    protected array $cityCodes = [
        '东京' => 'TYO', 'Paris' => 'PAR', '巴黎' => 'PAR', '纽约' => 'NYC', 'New York' => 'NYC',
        '北京' => 'BJS', '上海' => 'SHA', '伦敦' => 'LON', 'London' => 'LON',
        '首尔' => 'SEL', '曼谷' => 'BKK', '罗马' => 'ROM', 'Rome' => 'ROM',
        '香港' => 'HKG', '新加坡' => 'SIN', '台北' => 'TPE', '大阪' => 'OSA',
    ];

    /** 城市名 -> 菜系关键词 */
    protected array $cityCuisine = [
        '东京' => 'Japanese', 'Paris' => 'French', '巴黎' => 'French', '纽约' => 'American',
        '北京' => 'Chinese', '上海' => 'Chinese', '首尔' => 'Korean', '曼谷' => 'Thai',
        '罗马' => 'Italian', 'London' => 'British', '伦敦' => 'British',
        '香港' => 'Chinese', '新加坡' => 'Asian', '大阪' => 'Japanese',
    ];

    /** 中文城市名 -> 英文城市名（用于天气 API 查询更稳定） */
    protected array $weatherCityMap = [
        '东京' => 'Tokyo',
        '巴黎' => 'Paris',
        '纽约' => 'New York',
        '北京' => 'Beijing',
        '上海' => 'Shanghai',
        '伦敦' => 'London',
        '首尔' => 'Seoul',
        '曼谷' => 'Bangkok',
        '罗马' => 'Rome',
        '香港' => 'Hong Kong',
        '新加坡' => 'Singapore',
        '大阪' => 'Osaka',
    ];

    public function __construct()
    {
        $this->weatherApiKey = trim((string) config('services.openweather.key'));
        $this->amadeusClientId = trim((string) config('services.amadeus.client_id'));
        $this->amadeusClientSecret = trim((string) config('services.amadeus.client_secret'));
        $this->spoonacularApiKey = trim((string) config('services.spoonacular.key'));
    }

    /**
     * 获取城市天气（5天预报，免费版限制）
     */
    public function getWeather(string $city): array|false
    {
        $cacheKey = 'weather_' . $city . '_' . now()->format('Y-m-d-H');
        $cached = ApiCache::getCache($cacheKey);
        if ($cached) {
            return $cached->data;
        }

        if (empty($this->weatherApiKey)) {
            return $this->getMockWeatherData($city);
        }

        $queryCity = $this->normalizeWeatherCity($city);

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 TravelIdeasApp/1.0',
            ])->connectTimeout(6)->retry(2, 250)->timeout(12)->get('https://api.openweathermap.org/data/2.5/forecast', [
                'q' => $queryCity,
                'appid' => $this->weatherApiKey,
                'units' => 'metric',
                'lang' => 'en',
                'cnt' => 40,
            ]);

            if ($response->successful()) {
                $data = $this->formatWeatherData($response->json());
                ApiCache::setCache($cacheKey, 'weather', $data, 60);
                return $data;
            }
            Log::warning('Weather API response not successful', [
                'city' => $city,
                'query_city' => $queryCity,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // 第二通道：在某些环境下 Laravel HTTP 客户端会被拦截，尝试使用系统 curl 获取真实数据
            $curlData = $this->fetchWeatherByCurl($queryCity);
            if ($curlData !== false) {
                ApiCache::setCache($cacheKey, 'weather', $curlData, 60);
                return $curlData;
            }

            $fallbackRealData = $this->fetchWeatherFromOpenMeteo($city);
            if ($fallbackRealData !== false) {
                ApiCache::setCache($cacheKey, 'weather', $fallbackRealData, 60);
                return $fallbackRealData;
            }
        } catch (\Throwable $e) {
            Log::error('Weather API Error: ' . $e->getMessage());

            // 异常时也尝试走 curl 通道
            $curlData = $this->fetchWeatherByCurl($queryCity);
            if ($curlData !== false) {
                ApiCache::setCache($cacheKey, 'weather', $curlData, 60);
                return $curlData;
            }

            $fallbackRealData = $this->fetchWeatherFromOpenMeteo($city);
            if ($fallbackRealData !== false) {
                ApiCache::setCache($cacheKey, 'weather', $fallbackRealData, 60);
                return $fallbackRealData;
            }
        }
        return $this->getMockWeatherData($city);
    }

    /**
     * 获取酒店列表（需 Amadeus token）
     */
    public function getHotels(string $city, ?string $checkIn = null): array|false
    {
        $checkIn = $checkIn ?? now()->format('Y-m-d');
        $cacheKey = "hotels_{$city}_{$checkIn}";
        $cached = ApiCache::getCache($cacheKey);
        if ($cached) {
            return $cached->data;
        }

        if (empty($this->amadeusClientId) || empty($this->amadeusClientSecret)) {
            return $this->getMockHotelData($city);
        }

        try {
            $token = $this->getAmadeusToken();
            if (!$token) {
                return $this->getMockHotelData($city);
            }
            $cityCode = $this->getCityCode($city);
            $response = Http::withToken($token)->get(
                'https://api.amadeus.com/v1/reference-data/locations/hotels/by-city',
                ['cityCode' => $cityCode, 'radius' => 5, 'radiusUnit' => 'KM']
            );
            if ($response->successful()) {
                $data = $this->formatHotelData($response->json());
                ApiCache::setCache($cacheKey, 'hotels', $data, 30);
                return $data;
            }
            Log::warning('Hotel API response not successful', [
                'city' => $city,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Hotel API Error: ' . $e->getMessage());
        }
        return $this->getMockHotelData($city);
    }

    /**
     * 获取当地美食推荐
     */
    public function getFood(string $city): array|false
    {
        $cacheKey = 'food_' . $city . '_' . now()->format('Y-m-d');
        $cached = ApiCache::getCache($cacheKey);
        if ($cached) {
            return $cached->data;
        }

        if (empty($this->spoonacularApiKey)) {
            return $this->getMockFoodData($city);
        }

        try {
            $cuisine = $this->getCityCuisine($city);
            $response = Http::get('https://api.spoonacular.com/recipes/complexSearch', [
                'apiKey' => $this->spoonacularApiKey,
                'query' => $cuisine,
                'number' => 6,
                'addRecipeInformation' => true,
                'fillIngredients' => false,
            ]);
            if ($response->successful()) {
                $data = $this->formatFoodData($response->json());
                ApiCache::setCache($cacheKey, 'food', $data, 120);
                return $data;
            }
            Log::warning('Food API response not successful', [
                'city' => $city,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Food API Error: ' . $e->getMessage());
        }
        return $this->getMockFoodData($city);
    }

    protected function getAmadeusToken(): ?string
    {
        $response = Http::asForm()->post('https://api.amadeus.com/v1/security/oauth2/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->amadeusClientId,
            'client_secret' => $this->amadeusClientSecret,
        ]);
        if ($response->successful()) {
            return $response->json()['access_token'] ?? null;
        }
        return null;
    }

    protected function formatWeatherData(array $raw): array
    {
        $list = [];
        $byDate = [];
        foreach ($raw['list'] ?? [] as $item) {
            $date = date('Y-m-d', $item['dt']);
            if (!isset($byDate[$date])) {
                $byDate[$date] = ['min' => 999, 'max' => -999, 'desc' => '', 'icon' => ''];
            }
            $temp = $item['main']['temp'] ?? 0;
            $byDate[$date]['min'] = min($byDate[$date]['min'], $temp);
            $byDate[$date]['max'] = max($byDate[$date]['max'], $temp);
            $byDate[$date]['desc'] = $item['weather'][0]['description'] ?? '';
            $byDate[$date]['icon'] = 'https://openweathermap.org/img/wn/' . ($item['weather'][0]['icon'] ?? '01d') . '@2x.png';
        }
        foreach ($byDate as $date => $day) {
            $list[] = [
                'date' => $date,
                'temp' => round(($day['min'] + $day['max']) / 2),
                'temp_min' => round($day['min']),
                'temp_max' => round($day['max']),
                'description' => $day['desc'],
                'icon' => $day['icon'],
            ];
        }
        return [
            'list' => array_slice($list, 0, 7),
            'city' => $raw['city']['name'] ?? '',
            'source' => 'real',
        ];
    }

    protected function formatHotelData(array $raw): array
    {
        $hotels = [];
        foreach (($raw['data'] ?? []) as $h) {
            $hotels[] = [
                'name' => $h['name'] ?? 'Unknown Hotel',
                'rating' => $h['rating'] ?? null,
                'price' => $h['hotelId'] ? 'Price on request' : null,
            ];
        }
        if (empty($hotels)) {
            return $this->getMockHotelData('');
        }
        return $hotels;
    }

    protected function formatFoodData(array $raw): array
    {
        $foods = [];
        foreach ($raw['results'] ?? [] as $r) {
            $foods[] = [
                'id' => $r['id'] ?? 0,
                'title' => $r['title'] ?? '',
                'image' => $r['image'] ?? '',
                'summary' => strip_tags($r['summary'] ?? ''),
            ];
        }
        if (empty($foods)) {
            return $this->getMockFoodData('');
        }
        return $foods;
    }

    protected function getCityCode(string $city): string
    {
        foreach ($this->cityCodes as $name => $code) {
            if (stripos($city, $name) !== false || stripos($name, $city) !== false) {
                return $code;
            }
        }
        return 'TYO';
    }

    protected function getCityCuisine(string $city): string
    {
        foreach ($this->cityCuisine as $name => $cuisine) {
            if (stripos($city, $name) !== false || stripos($name, $city) !== false) {
                return $cuisine;
            }
        }
        return 'International';
    }

    protected function normalizeWeatherCity(string $city): string
    {
        foreach ($this->weatherCityMap as $zh => $en) {
            if (stripos($city, $zh) !== false) {
                return $en;
            }
        }

        return $city;
    }

    protected function fetchWeatherByCurl(string $queryCity): array|false
    {
        $url = 'https://api.openweathermap.org/data/2.5/forecast?q='
            . urlencode($queryCity)
            . '&appid=' . urlencode($this->weatherApiKey)
            . '&units=metric&lang=zh_cn&cnt=40';

        // PHP-FPM/CLI 的 PATH 可能不包含 curl，这里优先用绝对路径。
        $curlBin = '/usr/bin/curl';
        if (!file_exists($curlBin)) {
            $curlBin = trim((string) shell_exec('command -v curl 2>/dev/null'));
        }
        if ($curlBin === '') {
            Log::warning('Weather curl fallback unavailable: curl binary not found');
            return false;
        }

        $command = $curlBin . ' -sS --max-time 20 --connect-timeout 8 '
            . escapeshellarg($url) . ' 2>&1';
        $raw = shell_exec($command);
        if (!is_string($raw) || trim($raw) === '') {
            Log::warning('Weather curl fallback returned empty output', [
                'query_city' => $queryCity,
                'curl_bin' => $curlBin,
            ]);
            return false;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            Log::warning('Weather curl fallback returned non-JSON', [
                'query_city' => $queryCity,
                'curl_bin' => $curlBin,
                'output_preview' => mb_substr($raw, 0, 300),
            ]);
            return false;
        }

        if ((string) ($decoded['cod'] ?? '') === '200' || isset($decoded['list'])) {
            return $this->formatWeatherData($decoded);
        }

        Log::warning('Weather curl fallback not successful', [
            'query_city' => $queryCity,
            'response' => $decoded,
        ]);

        return false;
    }

    protected function fetchWeatherFromOpenMeteo(string $city): array|false
    {
        $cityCoords = [
            '东京' => [35.6895, 139.6917],
            'Tokyo' => [35.6895, 139.6917],
            '巴黎' => [48.8566, 2.3522],
            'Paris' => [48.8566, 2.3522],
            '纽约' => [40.7128, -74.0060],
            'New York' => [40.7128, -74.0060],
            '北京' => [39.9042, 116.4074],
            'Beijing' => [39.9042, 116.4074],
            '上海' => [31.2304, 121.4737],
            'Shanghai' => [31.2304, 121.4737],
            '伦敦' => [51.5072, -0.1276],
            'London' => [51.5072, -0.1276],
            '首尔' => [37.5665, 126.9780],
            'Seoul' => [37.5665, 126.9780],
            '曼谷' => [13.7563, 100.5018],
            'Bangkok' => [13.7563, 100.5018],
        ];

        $lat = null;
        $lon = null;
        foreach ($cityCoords as $name => $coord) {
            if (stripos($city, $name) !== false || stripos($name, $city) !== false) {
                [$lat, $lon] = $coord;
                break;
            }
        }
        if ($lat === null || $lon === null) {
            return false;
        }

        try {
            $response = Http::timeout(12)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $lat,
                'longitude' => $lon,
                'daily' => 'weathercode,temperature_2m_max,temperature_2m_min',
                'timezone' => 'auto',
                'forecast_days' => 5,
            ]);
            if (!$response->successful()) {
                return false;
            }

            $daily = $response->json('daily');
            if (!is_array($daily) || empty($daily['time'])) {
                return false;
            }

            $list = [];
            foreach ($daily['time'] as $i => $date) {
                $code = (int) ($daily['weathercode'][$i] ?? 0);
                $tempMin = (float) ($daily['temperature_2m_min'][$i] ?? 0);
                $tempMax = (float) ($daily['temperature_2m_max'][$i] ?? 0);
                $list[] = [
                    'date' => $date,
                    'temp' => round(($tempMin + $tempMax) / 2),
                    'temp_min' => round($tempMin),
                    'temp_max' => round($tempMax),
                    'description' => $this->mapOpenMeteoCode($code),
                    'icon' => 'https://openweathermap.org/img/wn/02d@2x.png',
                ];
            }

            return [
                'list' => $list,
                'city' => $city,
                'source' => 'real',
            ];
        } catch (\Throwable $e) {
            Log::warning('OpenMeteo fallback failed', ['city' => $city, 'message' => $e->getMessage()]);
            return false;
        }
    }

    protected function mapOpenMeteoCode(int $code): string
    {
        return match (true) {
            $code === 0 => 'Clear sky',
            in_array($code, [1, 2]) => 'Mainly clear',
            $code === 3 => 'Cloudy',
            in_array($code, [45, 48]) => 'Fog',
            in_array($code, [51, 53, 55, 56, 57]) => 'Drizzle',
            in_array($code, [61, 63, 65, 66, 67]) => 'Rain',
            in_array($code, [71, 73, 75, 77]) => 'Snow',
            in_array($code, [80, 81, 82]) => 'Rain showers',
            in_array($code, [85, 86]) => 'Snow showers',
            in_array($code, [95, 96, 99]) => 'Thunderstorm',
            default => 'Weather change',
        };
    }

    protected function getMockWeatherData(string $city): array
    {
        $list = [];
        for ($i = 0; $i < 5; $i++) {
            $date = now()->addDays($i)->format('Y-m-d');
            $list[] = [
                'date' => $date,
                'temp' => 18 + $i,
                'temp_min' => 15,
                'temp_max' => 22,
                'description' => 'Sunny',
                'icon' => 'https://openweathermap.org/img/wn/01d@2x.png',
            ];
        }
        return [
            'list' => $list,
            'city' => $city ?: 'Sample City',
            'source' => 'mock',
        ];
    }

    protected function getMockHotelData(string $city): array
    {
        return [
            ['name' => 'Sample Hotel A', 'rating' => '4.5', 'price' => 'From CNY 800'],
            ['name' => 'Sample Hotel B', 'rating' => '4.2', 'price' => 'From CNY 600'],
            ['name' => 'Sample Hotel C', 'rating' => '4.0', 'price' => 'From CNY 500'],
        ];
    }

    protected function getMockFoodData(string $city): array
    {
        return [
            ['id' => 1, 'title' => 'Local Signature Dish 1', 'image' => 'https://spoonacular.com/recipeImages/1-312x231.jpg', 'summary' => 'Recommended local flavor'],
            ['id' => 2, 'title' => 'Local Signature Dish 2', 'image' => 'https://spoonacular.com/recipeImages/2-312x231.jpg', 'summary' => 'Popular local pick'],
        ];
    }
}
