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
    protected ?string $spoonacularApiKey;
    protected ?string $exchangeRateApiKey;
    protected ?string $amapApiKey;
    protected ?string $rapidapiBookingKey;

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

    /** 城市名 -> 货币代码 */
    protected array $cityCurrency = [
        '东京' => 'JPY', 'Tokyo' => 'JPY',
        '巴黎' => 'EUR', 'Paris' => 'EUR',
        '纽约' => 'USD', 'New York' => 'USD',
        '北京' => 'CNY', 'Beijing' => 'CNY',
        '上海' => 'CNY', 'Shanghai' => 'CNY',
        '伦敦' => 'GBP', 'London' => 'GBP',
        '首尔' => 'KRW', 'Seoul' => 'KRW',
        '曼谷' => 'THB', 'Bangkok' => 'THB',
        '罗马' => 'EUR', 'Rome' => 'EUR',
        '香港' => 'HKD', 'Hong Kong' => 'HKD',
        '新加坡' => 'SGD', 'Singapore' => 'SGD',
        '大阪' => 'JPY', 'Osaka' => 'JPY',
    ];

    /** 城市名 -> 语言名称 */
    protected array $cityLanguage = [
        '东京' => 'Japanese', 'Tokyo' => 'Japanese',
        '巴黎' => 'French', 'Paris' => 'French',
        '纽约' => 'English', 'New York' => 'English',
        '北京' => 'Chinese', 'Beijing' => 'Chinese',
        '上海' => 'Chinese', 'Shanghai' => 'Chinese',
        '伦敦' => 'English', 'London' => 'English',
        '首尔' => 'Korean', 'Seoul' => 'Korean',
        '曼谷' => 'Thai', 'Bangkok' => 'Thai',
        '罗马' => 'Italian', 'Rome' => 'Italian',
        '香港' => 'Cantonese', 'Hong Kong' => 'Cantonese',
        '新加坡' => 'English', 'Singapore' => 'English',
        '大阪' => 'Japanese', 'Osaka' => 'Japanese',
    ];

    public function __construct()
    {
        $this->weatherApiKey = trim((string) config('services.openweather.key'));
        $this->amadeusClientId = trim((string) config('services.amadeus.client_id'));
        $this->amadeusClientSecret = trim((string) config('services.amadeus.client_secret'));
        $this->spoonacularApiKey = trim((string) config('services.spoonacular.key'));
        $this->exchangeRateApiKey = trim((string) config('services.exchangerate.key'));
        $this->amapApiKey = trim((string) config('services.amap.key'));
        $this->rapidapiBookingKey = trim((string) config('services.rapidapi_booking.key'));
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
     * 获取酒店列表（使用 RapidAPI Booking.com）
     */
    public function getHotels(string $city, ?string $checkIn = null): array|false
    {
        $cacheKey = "hotels_booking_" . md5($city);
        $cached = ApiCache::getCache($cacheKey);
        if ($cached) {
            return $cached->data;
        }

        if (empty($this->rapidapiBookingKey)) {
            return $this->getMockHotelData($city);
        }

        try {
            $queryCity = $this->normalizeWeatherCity($city);
            $destResponse = Http::withHeaders([
                'x-rapidapi-host' => 'booking-com15.p.rapidapi.com',
                'x-rapidapi-key' => $this->rapidapiBookingKey
            ])->timeout(12)->get('https://booking-com15.p.rapidapi.com/api/v1/hotels/searchDestination', [
                'query' => $queryCity
            ]);

            if ($destResponse->successful()) {
                $destData = $destResponse->json();
                if (($destData['status'] ?? false) && !empty($destData['data'])) {
                    $destId = $destData['data'][0]['dest_id'];
                    $searchType = $destData['data'][0]['search_type'];

                    $arrivalDate = now()->addMonths(1)->startOfMonth()->format('Y-m-d');
                    $departureDate = now()->addMonths(1)->startOfMonth()->addDays(2)->format('Y-m-d');
                    
                    $hotelsResponse = Http::withHeaders([
                        'x-rapidapi-host' => 'booking-com15.p.rapidapi.com',
                        'x-rapidapi-key' => $this->rapidapiBookingKey
                    ])->timeout(15)->get('https://booking-com15.p.rapidapi.com/api/v1/hotels/searchHotels', [
                        'dest_id' => $destId,
                        'search_type' => $searchType,
                        'arrival_date' => $arrivalDate,
                        'departure_date' => $departureDate,
                        'adults' => 1,
                        'room_qty' => 1
                    ]);

                    if ($hotelsResponse->successful()) {
                        $hotelsData = $hotelsResponse->json();
                        if (($hotelsData['status'] ?? false) && !empty($hotelsData['data']['hotels'])) {
                            $hotels = [];
                            foreach (array_slice($hotelsData['data']['hotels'], 0, 6) as $h) {
                                $prop = $h['property'] ?? [];
                                $price = $prop['priceBreakdown']['grossPrice'] ?? null;
                                $priceStr = $price ? ($price['currency'] . ' ' . round($price['value'])) : 'Price on request';
                                $img = null;
                                if (!empty($prop['photoUrls']) && is_array($prop['photoUrls'])) {
                                    $img = $prop['photoUrls'][0];
                                }
                                $hotels[] = [
                                    'name' => $prop['name'] ?? 'Unknown Hotel',
                                    'rating' => !empty($prop['reviewScoreWord']) ? ($prop['reviewScore'] . ' ' . $prop['reviewScoreWord']) : ($prop['reviewScore'] ?? 'New'),
                                    'price' => $priceStr,
                                    'image' => $img,
                                    'lat' => $prop['latitude'] ?? null,
                                    'lng' => $prop['longitude'] ?? null,
                                ];
                            }
                            if (!empty($hotels)) {
                                ApiCache::setCache($cacheKey, 'hotels', $hotels, 120);
                                return $hotels;
                            }
                        }
                    } else {
                        Log::warning('Booking Hotels API fail', ['body' => $hotelsResponse->body()]);
                    }
                }
            } else {
                Log::warning('Booking Dest API fail', ['body' => $destResponse->body()]);
            }
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

    public function getCityLanguageAndCurrency(string $city): array
    {
        $currency = 'USD';
        $language = 'English';
        foreach ($this->cityCurrency as $name => $curr) {
            if (stripos($city, $name) !== false || stripos($name, $city) !== false) {
                $currency = $curr;
                break;
            }
        }
        foreach ($this->cityLanguage as $name => $lang) {
            if (stripos($city, $name) !== false || stripos($name, $city) !== false) {
                $language = $lang;
                break;
            }
        }
        return ['currency' => $currency, 'language' => $language];
    }

    /**
     * 获取汇率数据
     */
    public function getExchangeRates(): array|false
    {
        $cacheKey = 'exchange_rates_hkd';
        $cached = ApiCache::getCache($cacheKey);
        if ($cached) {
            return $cached->data;
        }

        if (empty($this->exchangeRateApiKey)) {
            return $this->getMockExchangeRateData();
        }

        try {
            $response = Http::timeout(12)->get("https://v6.exchangerate-api.com/v6/{$this->exchangeRateApiKey}/latest/HKD");
            if ($response->successful()) {
                $data = $response->json();
                if (($data['result'] ?? '') === 'success' && !empty($data['conversion_rates'])) {
                    $result = [
                        'base' => 'HKD',
                        'rates' => $data['conversion_rates'],
                        'source' => 'real'
                    ];
                    ApiCache::setCache($cacheKey, 'exchangerate', $result, 120);
                    return $result;
                }
            }
            Log::warning('ExchangeRate API response not successful', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ExchangeRate API Error: ' . $e->getMessage());
        }
        return $this->getMockExchangeRateData();
    }

    /**
     * 获取高德当地景点推荐
     */
    public function getAmapAttractions(string $city): array|false
    {
        $cacheKey = 'amap_attractions_' . md5($city);
        $cached = ApiCache::getCache($cacheKey);
        if ($cached) {
            return $cached->data;
        }

        if (empty($this->amapApiKey)) {
            return $this->getMockAmapData($city);
        }

        $amapCity = $this->normalizeAmapCity($city);

        try {
            $response = Http::timeout(12)->get('https://restapi.amap.com/v3/place/text', [
                'key' => $this->amapApiKey,
                'keywords' => '景点',
                'city' => $amapCity,
                'citylimit' => 'true',
                'offset' => 6,
                'page' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '0') === '1' && !empty($data['pois'])) {
                    $attractions = [];
                    foreach ($data['pois'] as $poi) {
                        $photos = [];
                        if (!empty($poi['photos']) && is_array($poi['photos'])) {
                            foreach ($poi['photos'] as $p) {
                                if (!empty($p['url'])) {
                                    $photos[] = $p['url'];
                                }
                            }
                        }
                        $type = $poi['type'] ?? '景点';
                        $typeParts = explode(';', $type);
                        $type = end($typeParts);

                        $lng = null;
                        $lat = null;
                        if (!empty($poi['location'])) {
                            $parts = explode(',', (string)$poi['location']);
                            if (count($parts) === 2) {
                                $lng = (float)$parts[0];
                                $lat = (float)$parts[1];
                            }
                        }

                        $attractions[] = [
                            'name' => $poi['name'] ?? 'Unknown',
                            'type' => $type,
                            'address' => is_string($poi['address'] ?? null) ? $poi['address'] : 'No address provided',
                            'photos' => $photos,
                            'rating' => $poi['biz_ext']['rating'] ?? null,
                            'lat' => $lat,
                            'lng' => $lng,
                        ];
                    }

                    if (!empty($attractions)) {
                        $result = [
                            'list' => $attractions,
                            'source' => 'real',
                        ];
                        ApiCache::setCache($cacheKey, 'amap', $result, 120);
                        return $result;
                    }
                }
            }
            Log::warning('Amap API response not successful or empty', [
                'city' => $city,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Amap API Error: ' . $e->getMessage());
        }

        return $this->getMockAmapData($city);
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
        $cityName = $city ?: 'Sample City';
        $coords = $this->getCityCoordinates($city);
        $baseLat = $coords[0] !== 0 ? $coords[0] : 35.6895;
        $baseLng = $coords[1] !== 0 ? $coords[1] : 139.6917;

        return [
            ['name' => 'Grand ' . $cityName . ' Hotel', 'rating' => '8.5 Very Good', 'price' => 'USD 120', 'image' => 'https://cf.bstatic.com/xdata/images/hotel/square250/301726058.jpg?k=b4eab087e53f095166f2847fb5dcee9b2d354a78aa7677941fb621cda2ebef0b&o=', 'lat' => $baseLat + 0.01, 'lng' => $baseLng + 0.01],
            ['name' => 'The ' . $cityName . ' Resort', 'rating' => '9.0 Superb', 'price' => 'USD 180', 'image' => 'https://cf.bstatic.com/xdata/images/hotel/square250/244384074.jpg?k=ff36c6adacb3472be907bc0c410eefca6ba792a514d02636dcde103dc551d0ab&o=', 'lat' => $baseLat - 0.01, 'lng' => $baseLng - 0.01],
            ['name' => 'Budget Inn ' . $cityName, 'rating' => '7.8 Good', 'price' => 'USD 90', 'image' => 'https://cf.bstatic.com/xdata/images/hotel/square250/253905581.jpg?k=f8abdfb175dc8eeb59eaddb2add4ea7d6f5cfa4ba4a0ebfb0b5220c37bcea7f1&o=', 'lat' => $baseLat + 0.015, 'lng' => $baseLng - 0.015],
        ];
    }

    protected function getMockFoodData(string $city): array
    {
        return [
            ['id' => 1, 'title' => 'Local Signature Dish 1', 'image' => 'https://spoonacular.com/recipeImages/1-312x231.jpg', 'summary' => 'Recommended local flavor'],
            ['id' => 2, 'title' => 'Local Signature Dish 2', 'image' => 'https://spoonacular.com/recipeImages/2-312x231.jpg', 'summary' => 'Popular local pick'],
        ];
    }

    protected function getMockExchangeRateData(): array
    {
        return [
            'base' => 'HKD',
            'rates' => [
                'HKD' => 1.0,
                'USD' => 0.128,
                'EUR' => 0.117,
                'JPY' => 19.34,
                'CNY' => 0.923,
                'GBP' => 0.091,
                'KRW' => 174.5,
                'THB' => 4.5,
                'SGD' => 0.17,
            ],
            'source' => 'mock'
        ];
    }

    protected function getMockAmapData(string $city): array
    {
        $coords = $this->getCityCoordinates($city);
        $baseLat = $coords[0] !== 0 ? $coords[0] : 35.6895;
        $baseLng = $coords[1] !== 0 ? $coords[1] : 139.6917;

        return [
            'list' => [
                [
                    'name' => ($city ?: 'Sample City') . ' Museum',
                    'type' => 'Museum',
                    'address' => '123 Main Street',
                    'photos' => [],
                    'rating' => '4.8',
                    'lat' => $baseLat + 0.005,
                    'lng' => $baseLng + 0.005,
                ],
                [
                    'name' => ($city ?: 'Sample City') . ' Central Park',
                    'type' => 'Park',
                    'address' => '456 Green Ave',
                    'photos' => [],
                    'rating' => '4.5',
                    'lat' => $baseLat - 0.005,
                    'lng' => $baseLng - 0.005,
                ],
            ],
            'source' => 'mock'
        ];
    }

    protected function normalizeAmapCity(string $city): string
    {
        $map = [
            'Tokyo' => '东京',
            'Paris' => '巴黎',
            'New York' => '纽约',
            'London' => '伦敦',
            'Seoul' => '首尔',
            'Bangkok' => '曼谷',
            'Rome' => '罗马',
            'Hong Kong' => '香港',
            'Singapore' => '新加坡',
            'Osaka' => '大阪',
            'Beijing' => '北京',
            'Shanghai' => '上海',
        ];

        foreach ($map as $en => $zh) {
            if (stripos($city, $en) !== false) {
                return $zh;
            }
        }
        return $city;
    }

    public function getCityCoordinates(string $city): array
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
            '罗马' => [41.9028, 12.4964],
            'Rome' => [41.9028, 12.4964],
            '香港' => [22.3193, 114.1694],
            'Hong Kong' => [22.3193, 114.1694],
            '新加坡' => [1.3521, 103.8198],
            'Singapore' => [1.3521, 103.8198],
            '大阪' => [34.6937, 135.5023],
            'Osaka' => [34.6937, 135.5023]
        ];

        foreach ($cityCoords as $name => $coord) {
            if (stripos($city, $name) !== false) {
                return $coord;
            }
        }
        
        return [0, 0];
    }
}
