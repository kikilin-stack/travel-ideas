@extends('layouts.app')
@section('title', $idea->title)

@section('content')
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

        <div class="api-box" id="hotelBox">
            <h3>🏨 Recommended Hotels</h3>
            <div class="api-content" id="hotelContent">
                @if($hotelData && count($hotelData) > 0)
                    @foreach($hotelData as $hotel)
                    <div class="hotel-item">
                        <h4>{{ $hotel['name'] }}</h4>
                        <p class="rating">⭐ {{ $hotel['rating'] ?? 'No rating yet' }}</p>
                        <p class="price">💰 {{ $hotel['price'] ?? 'Price unavailable' }}</p>
                    </div>
                    @endforeach
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
});
</script>
@endpush
