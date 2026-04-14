@extends('layouts.app')
@section('title', 'Log in')

@section('content')
<div class="auth-container">
    <h2>Log in</h2>
    <form id="loginForm" class="auth-form" method="POST" action="{{ route('login') }}">
        @csrf
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required placeholder="Enter your email" value="{{ old('email') }}">
            <span class="error-msg" id="email-error"></span>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="6" placeholder="At least 6 characters">
            <span class="error-msg" id="password-error"></span>
        </div>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="remember" value="1"> Remember me
            </label>
        </div>
        <div class="form-group">
            <button type="submit" class="btn-primary">Log in</button>
        </div>
        <p class="form-link">No account yet? <a href="{{ route('register') }}">Create one</a></p>
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
        if (!email) { $('#email-error').text('Email is required'); ok = false; }
        if (!password) { $('#password-error').text('Password is required'); ok = false; }
        if (password.length < 6) { $('#password-error').text('Password must be at least 6 characters'); ok = false; }
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
                var msg = xhr.responseJSON?.errors?.email?.[0] || xhr.responseJSON?.message || 'Login failed. Please try again.';
                $('#email-error').text(msg);
            }
        });
    });
});
</script>
@endpush
