@extends('layouts.app')
@section('title', isset($idea) ? '编辑旅行想法' : '发布旅行想法')

@section('content')
<div class="container">
    <div class="form-container">
        <h1>{{ isset($idea) ? '编辑旅行想法' : '发布新想法' }}</h1>

        <form id="ideaForm" enctype="multipart/form-data">
            @csrf
            @if(isset($idea))
            @method('PUT')
            @endif

            <div class="form-group">
                <label for="title">标题 *</label>
                <input type="text" id="title" name="title" required maxlength="200"
                       value="{{ old('title', $idea->title ?? '') }}" placeholder="给你的旅行起个吸引人的标题">
            </div>

            <div class="form-group">
                <label for="destination">目的地 *</label>
                <input type="text" id="destination" name="destination" required maxlength="100"
                       value="{{ old('destination', $idea->destination ?? '') }}" placeholder="城市名称，如：东京、巴黎">
            </div>

            <div class="form-group">
                <label for="travel_date">计划出行日期</label>
                <input type="date" id="travel_date" name="travel_date"
                       value="{{ old('travel_date', isset($idea) && $idea->travel_date ? $idea->travel_date->format('Y-m-d') : '') }}">
            </div>

            <div class="form-group">
                <label for="tags">标签（用逗号分隔）</label>
                <input type="text" id="tags" name="tags" maxlength="255"
                       value="{{ old('tags', $idea->tags ?? '') }}" placeholder="例如：自由行,美食,购物">
            </div>

            <div class="form-group">
                <label for="cover_image">封面图片（可选，最大2MB）</label>
                <input type="file" id="cover_image" name="cover_image" accept="image/*">
                @if(isset($idea) && $idea->cover_image)
                <p class="current-file">当前封面：<img src="{{ asset('storage/' . $idea->cover_image) }}" width="120" alt=""></p>
                @endif
            </div>

            <div class="form-group">
                <label for="description">详细描述 *</label>
                <textarea id="description" name="description" required rows="10"
                          placeholder="描述你的旅行计划、期待、攻略等">{{ old('description', $idea->description ?? '') }}</textarea>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_public" value="1"
                           {{ !isset($idea) || $idea->is_public ? 'checked' : '' }}>
                    公开此想法（其他用户可见）
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary" id="submitBtn">
                    {{ isset($idea) ? '保存修改' : '发布想法' }}
                </button>
                <a href="{{ isset($idea) ? route('travel-ideas.show', $idea->id) : route('travel-ideas.index') }}" class="btn-secondary">取消</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('js')
<script>
$(function() {
    $('#cover_image').on('change', function() {
        var file = this.files[0];
        if (file && file.size > 2 * 1024 * 1024) {
            alert('图片大小不能超过2MB');
            $(this).val('');
        }
    });

    $('#ideaForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var url = '{{ isset($idea) ? route("travel-ideas.update", $idea->id) : route("travel-ideas.store") }}';
        var method = '{{ isset($idea) ? "PUT" : "POST" }}';
        var $btn = $('#submitBtn').prop('disabled', true);

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(res) {
                if (res.success) {
                    alert(res.message);
                    window.location.href = res.redirect || '{{ route("travel-ideas.index") }}';
                } else {
                    alert(res.message || '操作失败');
                }
            },
            error: function(xhr) {
                var data = xhr.responseJSON;
                if (data && data.errors) {
                    var msg = Object.values(data.errors).flat().join('\n');
                    alert('验证错误：\n' + msg);
                } else {
                    alert(data?.message || '提交失败，请重试');
                }
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>
@endpush
