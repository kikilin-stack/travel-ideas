@extends('layouts.app')
@section('title', $idea->title)

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<div class="container">
    <div class="idea-detail">
        <div class="detail-header">
            <h1>{{ $idea->title }}</h1>
            <div class="detail-meta">
                <span class="author">👤 {{ $idea->user->name }}</span>
                <span class="destination">📍 {{ $idea->destination }}</span>
                <span class="date">
                    📅
                    @if($idea->start_date && $idea->end_date)
                        {{ $idea->start_date->format('Y-m-d') }} - {{ $idea->end_date->format('Y-m-d') }}
                    @elseif($idea->start_date)
                        {{ $idea->start_date->format('Y-m-d') }}
                    @else
                        Not set
                    @endif
                </span>
            </div>
            @if($idea->tags)
            <div class="detail-tags">@foreach($idea->tag_array as $tag)<span class="tag">{{ trim($tag) }}</span>@endforeach</div>
            @endif
        </div>

        <div class="detail-content">
            @if($idea->cover_image)<img src="{{ asset('storage/' . $idea->cover_image) }}" class="cover-image" alt="">@endif
            <div class="description">{!! nl2br(e($idea->description)) !!}</div>
        </div>

        @auth
        @if(Auth::id() == $idea->user_id)
        <div class="detail-actions">
            <a href="{{ route('travel-ideas.edit', $idea->id) }}" class="btn-secondary">Edit</a>
            <button type="button" id="deleteBtn" class="btn-danger">Delete</button>
        </div>
        @endif
        @endauth
    </div>

    <div class="api-sections">
        <h2>Destination Insights</h2>
        
        <div id="interactiveMap" style="height: 400px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); z-index: 1; margin-bottom: 25px;"></div>

        <div class="api-box" id="weatherBox" data-city="{{ $idea->destination }}">
            <h3>🌤️ Weather Forecast</h3>
            @if(!empty($weatherData['source']) && $weatherData['source'] === 'mock')
                <p class="api-error" id="weatherSourceNotice">Currently showing fallback weather data (trying browser-side real-time weather).</p>
            @endif
            <div class="api-content" id="weatherContent">
                @if($weatherData && !empty($weatherData['list']))
                    @foreach($weatherData['list'] as $day)
                    <div class="weather-item">
                        <span class="date">{{ $day['date'] }}</span>
                        <img src="{{ $day['icon'] }}" alt="" class="weather-icon">
                        <span class="temp">{{ $day['temp'] }}°C</span>
                        <span class="desc">{{ $day['description'] ?? '' }}</span>
                    </div>
                    @endforeach
                @else
                    <p class="api-error">Weather data is temporarily unavailable</p>
                @endif
            </div>
        </div>

        <div class="api-box" id="exchangeRateBox">
            <h3>💱 Currency & Language</h3>
            <div class="api-content" id="exchangeRateContent" style="padding: 10px;">
                @if($exchangeRates && !empty($exchangeRates['rates']))
                    @if(!empty($exchangeRates['source']) && $exchangeRates['source'] === 'mock')
                        <p class="api-error" style="margin-bottom: 15px;">Currently showing mock exchange rates.</p>
                    @endif
                    <div class="exchange-widget" style="background: #f8f9fa; border-radius: 12px; padding: 20px; text-align: center; border: 1px solid #eee;">
                        <!-- Destination info badge -->
                        <div style="display:inline-block; background: #eef2ff; color: #4f46e5; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; margin-bottom: 20px;">
                            🗣️ Local Language: {{ $cityInfo['language'] }}
                        </div>

                        <!-- Converter Form -->
                        <div style="display: flex; align-items: center; justify-content: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                            <!-- HKD Input -->
                            <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                <label style="font-size: 12px; font-weight: 700; color: #6b7280; margin-bottom: 5px; text-transform: uppercase;">You Spend (HKD)</label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight:bold; color: #9ca3af;">$</span>
                                    <input type="number" id="hkdAmount" value="100" min="0" style="width: 140px; padding: 10px 10px 10px 25px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; font-weight: 600; outline: none; background: #fff;">
                                </div>
                            </div>

                            <!-- Exchange Icon -->
                            <div style="margin-top: 20px; color: #9ca3af;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <path d="M5 12h14"></path>
                                  <path d="m12 5 7 7-7 7"></path>
                                </svg>
                            </div>

                            <!-- Target Currency -->
                            <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                <label style="font-size: 12px; font-weight: 700; color: #6b7280; margin-bottom: 5px; text-transform: uppercase;">Target Currency</label>
                                <select id="targetCurrencySelect" style="width: 140px; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; font-weight: 600; background-color: #fff; outline: none; cursor: pointer;">
                                    @foreach($exchangeRates['rates'] as $cur => $rate)
                                        <option value="{{ $cur }}" data-rate="{{ $rate }}" {{ $cityInfo['currency'] === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Results Display -->
                        <div style="background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; display: inline-block; min-width: 250px;">
                            <div style="font-size: 2.2rem; font-weight: 800; color: #10b981; line-height: 1;">
                                <span id="convertedAmount">0.00</span> <span id="convertedCurrency" style="font-size: 1.25rem;">{{ $cityInfo['currency'] }}</span>
                            </div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-top: 8px; font-weight: 500;">
                                Rate: 1 HKD = <span id="currentRateLabel">0.00</span> <span id="currentRateCurrency">{{ $cityInfo['currency'] }}</span>
                            </div>
                        </div>
                    </div>
                @else
                    <p class="api-error">Exchange rates are temporarily unavailable</p>
                @endif
            </div>
        </div>

        <div class="api-box" id="budgetEstimatorBox">
            <h3>💰 Dynamic Trip Budget Estimator</h3>
            <div class="api-content" style="padding: 10px;">
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                    
                    <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                        <!-- Controls -->
                        <div style="flex: 1; min-width: 280px; padding-right: 20px; border-right: 1px dashed #e2e8f0;">
                            <!-- Days Slider -->
                            <div style="margin-bottom: 25px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <label style="font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase;">Duration</label>
                                    <span id="durationVal" style="font-weight: bold; color: #3b82f6;">3 Days</span>
                                </div>
                                <input type="range" id="durationSlider" min="1" max="30" value="3" style="width: 100%; cursor: pointer;">
                            </div>
                            
                            <!-- Travelers Slider -->
                            <div style="margin-bottom: 25px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <label style="font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase;">Travelers</label>
                                    <span id="travelersVal" style="font-weight: bold; color: #3b82f6;">2 People</span>
                                </div>
                                <input type="range" id="travelersSlider" min="1" max="10" value="2" style="width: 100%; cursor: pointer;">
                            </div>

                            <!-- Travel Style -->
                            <div>
                                <label style="font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 10px; display: block; text-transform: uppercase;">Travel Style</label>
                                <div style="display: flex; gap: 10px;" id="styleRadioGroup">
                                    <label style="flex:1; text-align:center; padding: 10px 5px; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.2s;" class="style-radio-label" data-value="budget">
                                        <input type="radio" name="travelStyle" value="budget" style="display:none;">
                                        🎒 Budget
                                    </label>
                                    <label style="flex:1; text-align:center; padding: 10px 5px; border: 2px solid #3b82f6; background: #eff6ff; color: #1d4ed8; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.2s;" class="style-radio-label active-style" data-value="standard">
                                        <input type="radio" name="travelStyle" value="standard" checked style="display:none;">
                                        💼 Standard
                                    </label>
                                    <label style="flex:1; text-align:center; padding: 10px 5px; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.2s;" class="style-radio-label" data-value="luxury">
                                        <input type="radio" name="travelStyle" value="luxury" style="display:none;">
                                        ✨ Luxury
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Readout -->
                        <div style="flex: 1; min-width: 250px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; background: #f8fafc; border-radius: 12px; padding: 20px;">
                            <h4 style="margin: 0 0 10px 0; color: #64748b; font-size: 14px; text-transform: uppercase;">Estimated Total</h4>
                            <div style="font-size: 2.8rem; font-weight: 800; color: #0f172a; line-height: 1;">
                                <span style="font-size: 1.5rem; color: #94a3b8; vertical-align: middle;">$</span><span id="totalHkdOut">0</span> <span style="font-size: 1.2rem; color: #64748b; font-weight: 600;">HKD</span>
                            </div>
                            <div style="margin-top: 15px; background: #e0e7ff; color: #4338ca; padding: 8px 16px; border-radius: 20px; font-weight: 600; font-size: 15px; display: inline-flex; align-items: center; gap: 8px;">
                                <span style="font-size: 1.2em;">≈</span>
                                <span id="totalLocalOut">0</span> <span id="budgetLocalCur">{{ $cityInfo['currency'] }}</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="api-box" id="hotelBox">
            <h3>🏨 Recommended Hotels</h3>
            <div class="api-content" id="hotelContent">
                @if($hotelData && count($hotelData) > 0)
                    <div class="hotels-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
                        @foreach($hotelData as $hotel)
                        <div class="hotel-card" style="border: 1px solid #eee; border-radius: 8px; overflow: hidden; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s;">
                            @if(!empty($hotel['image']))
                                <div class="hotel-img" style="height: 160px; background: url('{{ $hotel['image'] }}') center/cover; position: relative;">
                                    <span style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.75); color: #fff; font-size: 13px; font-weight:bold; padding: 4px 8px; border-radius: 4px;">{{ $hotel['price'] }}</span>
                                </div>
                            @else
                                <div class="hotel-img" style="height: 160px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; position: relative;">
                                    <span style="font-size: 2rem; color: #cbd5e1;">🏨</span>
                                    <span style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.75); color: #fff; font-size: 13px; font-weight:bold; padding: 4px 8px; border-radius: 4px;">{{ $hotel['price'] }}</span>
                                </div>
                            @endif
                            <div class="hotel-info" style="padding: 15px;">
                                <h4 style="margin: 0 0 8px 0; font-size: 16px; color: #1e293b; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ $hotel['name'] }}</h4>
                                <p style="margin: 0; font-size: 13.5px; color: #f59e0b; font-weight: 600;">⭐ {{ $hotel['rating'] ?? 'New / No rating' }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="api-error">Hotel data is temporarily unavailable</p>
                @endif
            </div>
        </div>

        <div class="api-box" id="foodBox">
            <h3>🍜 Local Food Picks</h3>
            <div class="api-content" id="foodContent">
                @if($foodData && count($foodData) > 0)
                    @foreach($foodData as $food)
                    <div class="food-item">
                        @if(!empty($food['image']))<img src="{{ $food['image'] }}" alt="{{ $food['title'] }}">@endif
                        <h4>{{ $food['title'] }}</h4>
                        <p>{{ Str::limit($food['summary'] ?? '', 80) }}</p>
                    </div>
                    @endforeach
                @else
                    <p class="api-error">Food data is temporarily unavailable</p>
                @endif
            </div>
        </div>

        <div class="api-box" id="amapBox">
            <h3>🗺️ Top Attractions</h3>
            <div class="api-content" id="amapContent">
                @if($amapData && !empty($amapData['list']))
                    @if(!empty($amapData['source']) && $amapData['source'] === 'mock')
                        <p class="api-error" style="margin-bottom: 15px;">Currently showing mock attractions.</p>
                    @endif
                    <div class="attractions-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
                        @foreach($amapData['list'] as $poi)
                        <div class="attraction-card" style="border: 1px solid #eee; border-radius: 8px; overflow: hidden; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                            @if(!empty($poi['photos']) && count($poi['photos']) > 0)
                                <div class="attraction-img" style="height: 150px; background: url('{{ $poi['photos'][0] }}') center/cover; position: relative;">
                                    <span style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.6); color: #fff; font-size: 11px; padding: 2px 6px; border-radius: 4px;">{{ $poi['type'] }}</span>
                                </div>
                            @else
                                <div class="attraction-img" style="height: 120px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; position: relative;">
                                    <span style="font-size: 2rem; color: #cbd5e1;">📸</span>
                                    <span style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.6); color: #fff; font-size: 11px; padding: 2px 6px; border-radius: 4px;">{{ $poi['type'] }}</span>
                                </div>
                            @endif
                            <div class="attraction-info" style="padding: 12px;">
                                <h4 style="margin: 0 0 8px 0; font-size: 16px; color: #1e293b;">{{ $poi['name'] }}</h4>
                                <p style="margin: 0; font-size: 13px; color: #64748b; margin-bottom: 8px;">📍 {{ $poi['address'] }}</p>
                                @if(!empty($poi['rating']))
                                    <p style="margin: 0; font-size: 13px; color: #f59e0b; font-weight: 600;">⭐ {{ $poi['rating'] }} / 5.0</p>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="api-error">Local attraction data is temporarily unavailable</p>
                @endif
            </div>
        </div>
    </div>

    <div class="comments-section">
        <h2>💬 Comments (<span id="commentCount">{{ $idea->comments->count() }}</span>)</h2>

        @auth
        <div class="comment-form">
            <textarea id="commentContent" maxlength="255" placeholder="Write a comment (max 255 characters)..."></textarea>
            <div class="char-count"><span id="charCount">0</span>/255</div>
            <button type="button" id="submitComment" class="btn-primary">Post Comment</button>
        </div>
        @else
        <p class="login-tip">Please <a href="{{ route('login') }}">log in</a> to leave a comment.</p>
        @endauth

        <div class="comments-list" id="commentsList">
            @foreach($idea->comments as $comment)
            <div class="comment-item" data-id="{{ $comment->id }}">
                <div class="comment-header">
                    <span class="username">{{ $comment->user->name }}</span>
                    <span class="time">{{ $comment->created_at->format('Y-m-d H:i') }}</span>
                </div>
                <p class="comment-content">{{ $comment->content }}</p>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
$(function() {
    var weatherIsMock = @json(!empty($weatherData['source']) && $weatherData['source'] === 'mock');
    if (weatherIsMock) {
        var city = ($('#weatherBox').data('city') || '').toString();
        var coordsMap = {
            'Tokyo': [35.6895, 139.6917], 'Paris': [48.8566, 2.3522], 'New York': [40.7128, -74.0060],
            'Beijing': [39.9042, 116.4074], 'Shanghai': [31.2304, 121.4737], 'London': [51.5072, -0.1276],
            'Seoul': [37.5665, 126.9780], 'Bangkok': [13.7563, 100.5018],
            '东京': [35.6895, 139.6917], '巴黎': [48.8566, 2.3522], '纽约': [40.7128, -74.0060],
            '北京': [39.9042, 116.4074], '上海': [31.2304, 121.4737], '伦敦': [51.5072, -0.1276],
            '首尔': [37.5665, 126.9780], '曼谷': [13.7563, 100.5018]
        };
        var codeMap = function(code) {
            if (code === 0) return 'Clear sky';
            if ([1,2].indexOf(code) >= 0) return 'Mainly clear';
            if (code === 3) return 'Cloudy';
            if ([45,48].indexOf(code) >= 0) return 'Fog';
            if ([51,53,55,56,57].indexOf(code) >= 0) return 'Drizzle';
            if ([61,63,65,66,67].indexOf(code) >= 0) return 'Rain';
            if ([71,73,75,77].indexOf(code) >= 0) return 'Snow';
            if ([80,81,82].indexOf(code) >= 0) return 'Rain showers';
            if ([85,86].indexOf(code) >= 0) return 'Snow showers';
            if ([95,96,99].indexOf(code) >= 0) return 'Thunderstorm';
            return 'Weather change';
        };
        var iconForCode = function(code) {
            return (code >= 51 || code === 3 || code === 45 || code === 48)
                ? 'https://openweathermap.org/img/wn/10d@2x.png'
                : 'https://openweathermap.org/img/wn/01d@2x.png';
        };

        var coord = null;
        Object.keys(coordsMap).forEach(function(name) {
            if (!coord && (city.indexOf(name) >= 0 || name.indexOf(city) >= 0)) coord = coordsMap[name];
        });

        if (coord) {
            var url = 'https://api.open-meteo.com/v1/forecast?latitude=' + coord[0] + '&longitude=' + coord[1] + '&daily=weathercode,temperature_2m_max,temperature_2m_min&timezone=auto&forecast_days=5';
            fetch(url).then(function(r){ return r.json(); }).then(function(data) {
                if (!data || !data.daily || !data.daily.time) return;
                var html = '';
                for (var i = 0; i < data.daily.time.length; i++) {
                    var code = Number(data.daily.weathercode[i] || 0);
                    var min = Number(data.daily.temperature_2m_min[i] || 0);
                    var max = Number(data.daily.temperature_2m_max[i] || 0);
                    var avg = Math.round((min + max) / 2);
                    html += '<div class="weather-item"><span class="date">' + data.daily.time[i] + '</span><img src="' + iconForCode(code) + '" alt="" class="weather-icon"><span class="temp">' + avg + '°C</span><span class="desc">' + codeMap(code) + '</span></div>';
                }
                if (html) {
                    $('#weatherContent').html(html);
                    $('#weatherSourceNotice').text('Now showing browser-fetched real-time weather data.');
                }
            }).catch(function() {});
        }
    }

    var latestCommentId = {{ (int) ($idea->comments->max('id') ?? 0) }};
    var commentsPollUrl = @json(route('comments.index', $idea->id));

    function buildCommentItem(c) {
        return $('<div class="comment-item"/>').attr('data-id', c.id)
            .append($('<div class="comment-header"/>')
                .append($('<span class="username"/>').text(c.user_name))
                .append($('<span class="time"/>').text(c.created_at || '')))
            .append($('<p class="comment-content"/>').text(c.content));
    }

    function prependIfNew(c) {
        if ($('#commentsList .comment-item[data-id="' + c.id + '"]').length) {
            return false;
        }
        $('#commentsList').prepend(buildCommentItem(c));
        return true;
    }

    function incrementCommentCount(delta) {
        var $n = $('#commentCount');
        var n = parseInt($n.text(), 10);
        if (isNaN(n)) n = 0;
        $n.text(n + delta);
    }

    function pollNewComments() {
        $.ajax({
            url: commentsPollUrl,
            method: 'GET',
            data: { after_id: latestCommentId },
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function(res) {
                if (!res.comments || !res.comments.length) return;
                var maxId = latestCommentId;
                res.comments.forEach(function(c) {
                    if (prependIfNew(c)) {
                        incrementCommentCount(1);
                        if (c.id > maxId) maxId = c.id;
                    }
                });
                latestCommentId = maxId;
            }
        });
    }

    setInterval(pollNewComments, 12000);

    $('#commentContent').on('input', function() { $('#charCount').text($(this).val().length); });

    $('#submitComment').on('click', function() {
        var content = $('#commentContent').val().trim();
        if (!content) { alert('Comment cannot be empty'); return; }
        if (content.length > 255) { alert('Comment must be 255 characters or less'); return; }
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: '{{ route("comments.store") }}', method: 'POST',
            data: {_token: '{{ csrf_token() }}', travel_idea_id: {{ $idea->id }}, content: content},
            headers: { 'Accept': 'application/json' },
            success: function(res) {
                if (res.success && res.comment) {
                    if (prependIfNew(res.comment)) {
                        incrementCommentCount(1);
                    }
                    if (res.comment.id > latestCommentId) {
                        latestCommentId = res.comment.id;
                    }
                    $('#commentContent').val('');
                    $('#charCount').text('0');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.errors?.content?.[0] || 'Failed to post comment. Please try again.';
                alert(msg);
            },
            complete: function() { $btn.prop('disabled', false); }
        });
    });

    $('#deleteBtn').on('click', function() {
        if (!confirm('Delete this travel idea? This action cannot be undone.')) return;
        $.ajax({
            url: '{{ route("travel-ideas.destroy", $idea->id) }}', method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' }, headers: { 'Accept': 'application/json' },
            success: function(res) { if (res.success) window.location.href = '{{ route("travel-ideas.index") }}'; },
            error: function() { alert('Delete failed. Please try again.'); }
        });
    });

    function updateExchangeRate() {
        var $opt = $('#targetCurrencySelect option:selected');
        if (!$opt.length) return;
        var rate = parseFloat($opt.data('rate') || 0);
        var cur = $opt.val();
        var amount = parseFloat($('#hkdAmount').val() || 0);
        var converted = (amount * rate).toFixed(2);
        $('#convertedAmount').text(converted);
        $('#convertedCurrency').text(cur);
        $('#currentRateLabel').text(rate.toFixed(4));
        $('#currentRateCurrency').text(cur);
    }

    if ($('#exchangeRateBox').length) {
        $('#hkdAmount').on('input', updateExchangeRate);
        $('#targetCurrencySelect').on('change', updateExchangeRate);
        updateExchangeRate();
    }

    // Interactive Map Initialization
    @if(isset($cityCoords) && $cityCoords[0] !== 0)
    if ($('#interactiveMap').length) {
        var map = L.map('interactiveMap').setView([{{ $cityCoords[0] }}, {{ $cityCoords[1] }}], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var redIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        });

        // Attractions (Amap) - Red
        @if(!empty($amapData['list']))
            @foreach($amapData['list'] as $poi)
                @if(!empty($poi['lat']) && !empty($poi['lng']))
                    L.marker([{{ $poi['lat'] }}, {{ $poi['lng'] }}], {icon: redIcon}).addTo(map)
                        .bindPopup('<b>{{ addslashes($poi['name']) }}</b><br>🎯 Top Attraction');
                @endif
            @endforeach
        @endif

        // Hotels (Booking) - Blue
        @if(!empty($hotelData))
            @foreach($hotelData as $hotel)
                @if(!empty($hotel['lat']) && !empty($hotel['lng']))
                    L.marker([{{ $hotel['lat'] }}, {{ $hotel['lng'] }}]).addTo(map)
                        .bindPopup('<b>{{ addslashes($hotel['name']) }}</b><br>🏨 Hotel<br>Price: {{ addslashes($hotel['price']) }}');
                @endif
            @endforeach
        @endif
    }
    @endif

    // Dynamic Budget Estimator Logic
    if ($('#budgetEstimatorBox').length) {
        var baseRate = 0;
        @if(isset($cityInfo['currency']) && isset($exchangeRates['rates'][$cityInfo['currency']]))
            baseRate = {{ $exchangeRates['rates'][$cityInfo['currency']] }};
        @endif

        function calculateBudget() {
            var days = parseInt($('#durationSlider').val()) || 1;
            var people = parseInt($('#travelersSlider').val()) || 1;
            var style = $('input[name="travelStyle"]:checked').val() || 'standard';

            $('#durationVal').text(days + (days === 1 ? ' Day' : ' Days'));
            $('#travelersVal').text(people + (people === 1 ? ' Person' : ' People'));

            var hotelRooms = Math.ceil(people / 2);
            var foodPPD, hotelPRPD; // Per Person Day, Per Room Per Day (HKD)

            switch(style) {
                case 'budget': foodPPD = 200; hotelPRPD = 300; break;
                case 'luxury': foodPPD = 1500; hotelPRPD = 2500; break;
                case 'standard': 
                default: foodPPD = 500; hotelPRPD = 800; break;
            }

            var totalHkd = (foodPPD * people * days) + (hotelPRPD * hotelRooms * days);
            var totalLocal = totalHkd * baseRate;

            // Animate or set
            $('#totalHkdOut').text(totalHkd.toLocaleString());
            $('#totalLocalOut').text(totalLocal.toLocaleString(undefined, {maximumFractionDigits:0}));
        }

        // Bind events
        $('#durationSlider, #travelersSlider').on('input', calculateBudget);
        $('.style-radio-label').on('click', function() {
            $('.style-radio-label').removeClass('active-style').css({'border': '1px solid #cbd5e1', 'background':'transparent', 'color':'inherit'});
            $(this).addClass('active-style').css({'border': '2px solid #3b82f6', 'background':'#eff6ff', 'color':'#1d4ed8'});
            $(this).find('input').prop('checked', true);
            calculateBudget();
        });

        // Initial calculation
        calculateBudget();
    }
});
</script>
@endpush
