@extends('layouts.app')
@section('title', '用户登录')

@section('content')
<div class="auth-container">
    <h2>用户登录</h2>
    <form id="loginForm" class="auth-form" method="POST" action="{{ route('login') }}">
        @csrf
        <div class="form-group">
            <label for="email">邮箱</label>
            <input type="email" id="email" name="email" required placeholder="请输入邮箱" value="{{ old('email') }}">
            <span class="error-msg" id="email-error"></span>
        </div>
        <div class="form-group">
            <label for="password">密码</label>
            <input type="password" id="password" name="password" required minlength="6" placeholder="至少6位密码">
            <span class="error-msg" id="password-error"></span>
        </div>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="remember" value="1"> 记住我
            </label>
        </div>
        <div class="form-group">
            <button type="submit" class="btn-primary">登录</button>
        </div>
        <p class="form-link">还没有账号？<a href="{{ route('register') }}">立即注册</a></p>
    </form>
</div>
@endsection

@push('js')
<script>
$(function() {
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        $form.find('.error-msg').text('');
        var email = $('#email').val().trim();
        var password = $('#password').val();
        var ok = true;
        if (!email) { $('#email-error').text('请输入邮箱'); ok = false; }
        if (!password) { $('#password-error').text('请输入密码'); ok = false; }
        if (password.length < 6) { $('#password-error').text('密码至少6位'); ok = false; }
        if (!ok) return;
        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function(res) {
                window.location.href = res.redirect || '{{ route("travel-ideas.index") }}';
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.errors?.email?.[0] || xhr.responseJSON?.message || '登录失败，请重试';
                $('#email-error').text(msg);
            }
        });
    });
});
</script>
@endpush
