@extends('layouts.app')
@section('title', '用户注册')

@section('content')
<div class="auth-container">
    <h2>用户注册</h2>
    <form id="registerForm" class="auth-form" method="POST" action="{{ route('register') }}">
        @csrf
        <div class="form-group">
            <label for="name">姓名</label>
            <input type="text" id="name" name="name" required minlength="2" maxlength="50" placeholder="2-50个字符" value="{{ old('name') }}">
            <span class="error-msg" id="name-error"></span>
        </div>
        <div class="form-group">
            <label for="reg_email">邮箱</label>
            <input type="email" id="reg_email" name="email" required placeholder="请输入有效邮箱" value="{{ old('email') }}">
            <span class="error-msg" id="email-error"></span>
        </div>
        <div class="form-group">
            <label for="password">密码</label>
            <input type="password" id="password" name="password" required minlength="6" placeholder="至少6位密码">
            <span class="error-msg" id="password-error"></span>
        </div>
        <div class="form-group">
            <label for="password_confirmation">确认密码</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="再次输入密码">
        </div>
        <div class="form-group">
            <button type="submit" class="btn-primary">注册</button>
        </div>
        <p class="form-link">已有账号？<a href="{{ route('login') }}">立即登录</a></p>
    </form>
</div>
@endsection

@push('js')
<script>
$(function() {
    $('#registerForm').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        $form.find('.error-msg').text('');
        var name = $('#name').val().trim();
        var email = $('#reg_email').val().trim();
        var password = $('#password').val();
        var confirm = $('#password_confirmation').val();
        var ok = true;
        if (name.length < 2) { $('#name-error').text('姓名至少2个字符'); ok = false; }
        if (!email) { $('#email-error').text('请输入邮箱'); ok = false; }
        if (!password) { $('#password-error').text('请输入密码'); ok = false; }
        if (password.length < 6) { $('#password-error').text('密码至少6位'); ok = false; }
        if (password !== confirm) { $('#password-error').text('两次密码不一致'); ok = false; }
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
                var data = xhr.responseJSON;
                if (data?.errors) {
                    $.each(data.errors, function(field, msgs) {
                        var id = field === 'email' ? 'email-error' : field + '-error';
                        $('#' + id).text(msgs[0]);
                    });
                } else {
                    $('#email-error').text(data?.message || '注册失败，请重试');
                }
            }
        });
    });
});
</script>
@endpush
