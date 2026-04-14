@extends('layouts.app')
@section('title', 'Travel Ideas')

@section('content')
<div class="container">
    <div class="page-header">
        <h1>Explore Travel Ideas</h1>
        <form class="search-box" action="{{ route('travel-ideas.index') }}" method="GET">
            <input type="text" id="searchInput" name="q" placeholder="Search by destination or tags…" value="{{ request('q') }}">
            <button type="submit" id="searchBtn" class="btn-secondary">Search</button>
        </form>
    </div>

    <p class="search-results-summary" role="status">
        <strong>{{ number_format($ideas->total()) }}</strong>
        matching {{ Str::plural('record', $ideas->total()) }}
    </p>

    <div class="ideas-grid" id="ideasGrid">
        @forelse($ideas as $idea)
        @php
            $tripRef = $idea->start_date ?? $idea->end_date ?? $idea->travel_date;
        @endphp
        <div class="idea-card" data-id="{{ $idea->id }}">
            @if($idea->cover_image)
            <div class="card-image"><img src="{{ asset('storage/' . $idea->cover_image) }}" alt="{{ $idea->title }}"></div>
            @else
            <div class="card-image card-image-placeholder">📷</div>
            @endif
            <div class="card-body">
                <h3 class="card-title">{{ $idea->title }}</h3>
                <p class="card-destination"><span class="icon">📍</span>{{ $idea->destination }}</p>
                <ul class="card-search-facts">
                    <li><span class="label">Date</span> <span class="value">{{ $tripRef ? $tripRef->format('m/Y') : '—' }}</span></li>
                    <li><span class="label">Comments</span> <span class="value">{{ number_format($idea->comments_count) }}</span></li>
                </ul>
                <a href="{{ route('travel-ideas.show', $idea->id) }}" class="btn-view">View Details</a>
            </div>
        </div>
        @empty
        <div class="empty-state">
            @if(trim((string) request('q', '')) !== '')
                <p>No travel ideas match your search.</p>
            @else
                <p>No travel ideas yet. @auth <a href="{{ route('travel-ideas.create') }}">Create the first idea</a> @else Please <a href="{{ route('login') }}">log in</a> to create one. @endauth</p>
            @endif
        </div>
        @endforelse
    </div>

    <div class="pagination-wrap">{{ $ideas->links() }}</div>
</div>
@endsection
