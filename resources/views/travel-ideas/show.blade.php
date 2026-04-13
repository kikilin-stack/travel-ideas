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
                <span class="date">📅 {{ $idea->travel_date ? $idea->travel_date->format('Y-m-d') : '未定' }}</span>
            </div>
            @if($idea->tags)
            <div class="detail-tags">
                @foreach($idea->tag_array as $tag)
                <span class="tag">{{ trim($tag) }}</span>
                @endforeach
            </div>
            @endif
        </div>

        <div class="detail-content">
            @if($idea->cover_image)
            <img src="{{ asset('storage/' . $idea->cover_image) }}" class="cover-image" alt="">
            @endif
            <div class="description">{!! nl2br(e($idea->description)) !!}</div>
        </div>

        @auth
        @if(Auth::id() == $idea->user_id)
        <div class="detail-actions">
            <a href="{{ route('travel-ideas.edit', $idea->id) }}" class="btn-secondary">编辑</a>
            <button type="button" id="deleteBtn" class="btn-danger">删除</button>
        </div>
        @endif
        @endauth
    </div>

    <div class="api-sections">
        <h2>目的地信息</h2>

        <div class="api-box" id="weatherBox" data-city="{{ $idea->destination }}">
            <h3>🌤️ 天气预报</h3>
            @if(!empty($weatherData['source']) && $weatherData['source'] === 'mock')
                <p class="api-error" id="weatherSourceNotice">当前显示的是模拟天气数据（正在尝试浏览器直连实时天气）</p>
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
                    <p class="api-error">天气数据暂时无法加载</p>
                @endif
            </div>
        </div>

        <div class="api-box" id="hotelBox">
            <h3>🏨 推荐酒店</h3>
            <div class="api-content" id="hotelContent">
                @if($hotelData && count($hotelData) > 0)
                    @foreach($hotelData as $hotel)
                    <div class="hotel-item">
                        <h4>{{ $hotel['name'] }}</h4>
                        <p class="rating">⭐ {{ $hotel['rating'] ?? '暂无评分' }}</p>
                        <p class="price">💰 {{ $hotel['price'] ?? '价格面议' }}</p>
                    </div>
                    @endforeach
                @else
                    <p class="api-error">酒店数据暂时无法加载</p>
                @endif
            </div>
        </div>

        <div class="api-box" id="foodBox">
            <h3>🍜 当地美食推荐</h3>
            <div class="api-content" id="foodContent">
                @if($foodData && count($foodData) > 0)
                    @foreach($foodData as $food)
                    <div class="food-item">
                        @if(!empty($food['image']))
                        <img src="{{ $food['image'] }}" alt="{{ $food['title'] }}">
                        @endif
                        <h4>{{ $food['title'] }}</h4>
                        <p>{{ Str::limit($food['summary'] ?? '', 80) }}</p>
                    </div>
                    @endforeach
                @else
                    <p class="api-error">美食数据暂时无法加载</p>
                @endif
            </div>
        </div>
    </div>

    <div class="comments-section">
        <h2>💬 评论 ({{ $idea->comments->count() }})</h2>

        @auth
        <div class="comment-form">
            <textarea id="commentContent" maxlength="255" placeholder="写下你的评论（最多255字）..."></textarea>
            <div class="char-count"><span id="charCount">0</span>/255</div>
            <button type="button" id="submitComment" class="btn-primary">发表评论</button>
        </div>
        @else
        <p class="login-tip">请<a href="{{ route('login') }}">登录</a>后发表评论</p>
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
    // 后端网络受限时，前端直接请求 Open-Meteo 实时天气并覆盖 mock 数据
    var weatherIsMock = @json(!empty($weatherData['source']) && $weatherData['source'] === 'mock');
    if (weatherIsMock) {
        var city = ($('#weatherBox').data('city') || '').toString();
        var coordsMap = {
            '东京': [35.6895, 139.6917], 'Tokyo': [35.6895, 139.6917],
            '巴黎': [48.8566, 2.3522], 'Paris': [48.8566, 2.3522],
            '纽约': [40.7128, -74.0060], 'New York': [40.7128, -74.0060],
            '北京': [39.9042, 116.4074], 'Beijing': [39.9042, 116.4074],
            '上海': [31.2304, 121.4737], 'Shanghai': [31.2304, 121.4737],
            '伦敦': [51.5072, -0.1276], 'London': [51.5072, -0.1276],
            '首尔': [37.5665, 126.9780], 'Seoul': [37.5665, 126.9780],
            '曼谷': [13.7563, 100.5018], 'Bangkok': [13.7563, 100.5018]
        };
        var codeMap = function(code) {
            if (code === 0) return '晴';
            if ([1, 2].indexOf(code) >= 0) return '晴，少云';
            if (code === 3) return '多云';
            if ([45, 48].indexOf(code) >= 0) return '有雾';
            if ([51, 53, 55, 56, 57].indexOf(code) >= 0) return '毛毛雨';
            if ([61, 63, 65, 66, 67].indexOf(code) >= 0) return '降雨';
            if ([71, 73, 75, 77].indexOf(code) >= 0) return '降雪';
            if ([80, 81, 82].indexOf(code) >= 0) return '阵雨';
            if ([85, 86].indexOf(code) >= 0) return '阵雪';
            if ([95, 96, 99].indexOf(code) >= 0) return '雷雨';
            return '天气变化';
        };
        var iconForCode = function(code) {
            return (code >= 51 || code === 3 || code === 45 || code === 48)
                ? 'https://openweathermap.org/img/wn/10d@2x.png'
                : 'https://openweathermap.org/img/wn/01d@2x.png';
        };
        var coord = null;
        Object.keys(coordsMap).forEach(function(name) {
            if (!coord && (city.indexOf(name) >= 0 || name.indexOf(city) >= 0)) {
                coord = coordsMap[name];
            }
        });
        if (coord) {
            var url = 'https://api.open-meteo.com/v1/forecast?latitude=' + coord[0] +
                '&longitude=' + coord[1] +
                '&daily=weathercode,temperature_2m_max,temperature_2m_min&timezone=auto&forecast_days=5';
            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !data.daily || !data.daily.time) return;
                    var html = '';
                    for (var i = 0; i < data.daily.time.length; i++) {
                        var code = Number(data.daily.weathercode[i] || 0);
                        var min = Number(data.daily.temperature_2m_min[i] || 0);
                        var max = Number(data.daily.temperature_2m_max[i] || 0);
                        var avg = Math.round((min + max) / 2);
                        html += '<div class="weather-item">' +
                            '<span class="date">' + data.daily.time[i] + '</span>' +
                            '<img src="' + iconForCode(code) + '" alt="" class="weather-icon">' +
                            '<span class="temp">' + avg + '°C</span>' +
                            '<span class="desc">' + codeMap(code) + '</span>' +
                            '</div>';
                    }
                    if (html) {
                        $('#weatherContent').html(html);
                        $('#weatherSourceNotice').text('当前显示的是浏览器直连实时天气数据');
                    }
                })
                .catch(function() {});
        }
    }

    $('#commentContent').on('input', function() {
        $('#charCount').text($(this).val().length);
    });

    $('#submitComment').on('click', function() {
        var content = $('#commentContent').val().trim();
        if (!content) {
            alert('评论内容不能为空');
            return;
        }
        if (content.length > 255) {
            alert('评论不能超过255字');
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: '{{ route("comments.store") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                travel_idea_id: {{ $idea->id }},
                content: content
            },
            headers: { 'Accept': 'application/json' },
            success: function(res) {
                if (res.success && res.comment) {
                    var html = '<div class="comment-item">' +
                        '<div class="comment-header">' +
                        '<span class="username">' + res.comment.user_name + '</span>' +
                        '<span class="time">' + (res.comment.created_at || '刚刚') + '</span></div>' +
                        '<p class="comment-content">' + res.comment.content.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p></div>';
                    $('#commentsList').prepend(html);
                    $('#commentContent').val('');
                    $('#charCount').text('0');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.errors?.content?.[0] || '评论发表失败，请重试';
                alert(msg);
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    $('#deleteBtn').on('click', function() {
        if (!confirm('确定要删除这个旅行想法吗？此操作不可恢复。')) return;
        $.ajax({
            url: '{{ route("travel-ideas.destroy", $idea->id) }}',
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            headers: { 'Accept': 'application/json' },
            success: function(res) {
                if (res.success) {
                    window.location.href = '{{ route("travel-ideas.index") }}';
                }
            },
            error: function() {
                alert('删除失败，请重试');
            }
        });
    });
});
</script>
@endpush
