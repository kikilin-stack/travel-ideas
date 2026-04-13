@extends('layouts.app')
@section('title', '旅行想法列表')

@section('content')
<div class="container">
    <div class="page-header">
        <h1>探索旅行想法</h1>
        <form class="search-box" action="{{ route('travel-ideas.index') }}" method="GET">
            <input type="text" id="searchInput" name="q" placeholder="搜索目的地、标签、标题..." value="{{ request('q') }}">
            <button type="submit" id="searchBtn" class="btn-secondary">搜索</button>
        </form>
    </div>

    <div class="ideas-grid" id="ideasGrid">
        @forelse($ideas as $idea)
        <div class="idea-card" data-id="{{ $idea->id }}">
            @if($idea->cover_image)
            <div class="card-image">
                <img src="{{ asset('storage/' . $idea->cover_image) }}" alt="{{ $idea->title }}">
            </div>
            @else
            <div class="card-image card-image-placeholder">📷</div>
            @endif
            <div class="card-body">
                <h3 class="card-title">{{ $idea->title }}</h3>
                <p class="card-destination">
                    <span class="icon">📍</span>{{ $idea->destination }}
                </p>
                <p class="card-desc">{{ Str::limit($idea->description, 100) }}</p>
                @if($idea->tags)
                <div class="card-tags">
                    @foreach($idea->tag_array as $tag)
                    <span class="tag">{{ trim($tag) }}</span>
                    @endforeach
                </div>
                @endif
                <div class="card-meta">
                    <span class="author">{{ $idea->user->name }}</span>
                    <span class="date">{{ $idea->created_at->format('Y-m-d') }}</span>
                </div>
                <a href="{{ route('travel-ideas.show', $idea->id) }}" class="btn-view">查看详情</a>
            </div>
        </div>
        @empty
        <div class="empty-state">
            <p>暂无旅行想法，@auth<a href="{{ route('travel-ideas.create') }}">发布第一个想法</a>@else 请<a href="{{ route('login') }}">登录</a>后发布 @endauth</p>
        </div>
        @endforelse
    </div>

    <div class="pagination-wrap">
        {{ $ideas->links() }}
    </div>
</div>
@endsection
