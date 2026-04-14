@extends('layouts.app')
@section('title', isset($idea) ? 'Edit Travel Idea' : 'Create Travel Idea')

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endpush

@section('content')
<div class="container">
    <div class="form-container">
        <h1>{{ isset($idea) ? 'Edit Travel Idea' : 'Create New Travel Idea' }}</h1>

        <form id="ideaForm" enctype="multipart/form-data">
            @csrf
            @if(isset($idea)) @method('PUT') @endif

            <div class="form-group">
                <label for="title">Title *</label>
                <input type="text" id="title" name="title" required maxlength="200" value="{{ old('title', $idea->title ?? '') }}" placeholder="Add a catchy title for your trip">
            </div>

            <div class="form-group">
                <label for="destination">Destination *</label>
                <input type="text" id="destination" name="destination" required maxlength="100" value="{{ old('destination', $idea->destination ?? '') }}" placeholder="City name, e.g. Tokyo, Paris">
            </div>

            <div class="form-group">
                <label for="start_date">Start date</label>
                <input type="text" id="start_date" name="start_date" class="date-en" autocomplete="off" placeholder="YYYY-MM-DD"
                       value="{{ old('start_date', isset($idea) && $idea->start_date ? $idea->start_date->format('Y-m-d') : '') }}"
                       lang="en-US" inputmode="numeric">
            </div>

            <div class="form-group">
                <label for="end_date">End date</label>
                <input type="text" id="end_date" name="end_date" class="date-en" autocomplete="off" placeholder="YYYY-MM-DD"
                       value="{{ old('end_date', isset($idea) && $idea->end_date ? $idea->end_date->format('Y-m-d') : '') }}"
                       lang="en-US" inputmode="numeric">
            </div>

            <div class="form-group">
                <label for="tags">Tags (comma-separated)</label>
                <input type="text" id="tags" name="tags" maxlength="255" value="{{ old('tags', $idea->tags ?? '') }}" placeholder="e.g. food, shopping, museum">
            </div>

            <div class="form-group">
                <label for="cover_image">Cover image (optional, max 2MB)</label>
                <input type="file" id="cover_image" name="cover_image" accept="image/*">
                @if(isset($idea) && $idea->cover_image)
                <p class="current-file">Current cover: <img src="{{ asset('storage/' . $idea->cover_image) }}" width="120" alt=""></p>
                @endif
            </div>

            <div class="form-group">
                <label for="description">Description *</label>
                <textarea id="description" name="description" required rows="10" placeholder="Share your plan, highlights, and tips...">{{ old('description', $idea->description ?? '') }}</textarea>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_public" value="1" {{ !isset($idea) || $idea->is_public ? 'checked' : '' }}>
                    Make this idea public
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary" id="submitBtn">{{ isset($idea) ? 'Save Changes' : 'Publish Idea' }}</button>
                <a href="{{ isset($idea) ? route('travel-ideas.show', $idea->id) : route('travel-ideas.index') }}" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
$(function() {
    var startVal = $('#start_date').val() || null;
    var endPicker = flatpickr('#end_date', {
        dateFormat: 'Y-m-d',
        allowInput: true,
        minDate: startVal
    });
    flatpickr('#start_date', {
        dateFormat: 'Y-m-d',
        allowInput: true,
        onChange: function(selectedDates) {
            endPicker.set('minDate', selectedDates[0] || null);
        }
    });

    $('#cover_image').on('change', function() {
        var file = this.files[0];
        if (file && file.size > 2 * 1024 * 1024) {
            alert('Image size must be less than 2MB');
            $(this).val('');
        }
    });

    $('#ideaForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var url = '{{ isset($idea) ? route("travel-ideas.update", $idea->id) : route("travel-ideas.store") }}';
        var $btn = $('#submitBtn').prop('disabled', true);

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {'Accept': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            success: function(res) {
                if (res.success) {
                    alert(res.message);
                    window.location.href = res.redirect || '{{ route("travel-ideas.index") }}';
                } else {
                    alert(res.message || 'Action failed');
                }
            },
            error: function(xhr) {
                var data = xhr.responseJSON;
                if (data && data.errors) {
                    var msg = Object.values(data.errors).flat().join('\n');
                    alert('Validation error:\n' + msg);
                } else {
                    alert(data?.message || 'Submission failed. Please try again.');
                }
            },
            complete: function() { $btn.prop('disabled', false); }
        });
    });
});
</script>
@endpush
