@extends('layouts.app')
@section('title', 'Sign up')

@section('content')
<div class="auth-container">
    <h2>Create account</h2>
    <form id="registerForm" class="auth-form" method="POST" action="{{ route('register') }}">
        @csrf
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required minlength="2" maxlength="50" placeholder="2-50 characters" value="{{ old('name') }}">
            <span class="error-msg" id="name-error"></span>
        </div>
        <div class="form-group">
            <label for="reg_email">Email</label>
            <input type="email" id="reg_email" name="email" required placeholder="Enter a valid email" value="{{ old('email') }}">
            <span class="error-msg" id="email-error"></span>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="6" placeholder="At least 6 characters">
            <span class="error-msg" id="password-error"></span>
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="Type your password again">
        </div>
        <div class="form-group">
            <button type="submit" class="btn-primary">Sign up</button>
        </div>
        <p class="form-link">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
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

        if (name.length < 2) { $('#name-error').text('Name must be at least 2 characters'); ok = false; }
        if (!email) { $('#email-error').text('Email is required'); ok = false; }
        if (!password) { $('#password-error').text('Password is required'); ok = false; }
        if (password.length < 6) { $('#password-error').text('Password must be at least 6 characters'); ok = false; }
        if (password !== confirm) { $('#password-error').text('Passwords do not match'); ok = false; }
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
                    $('#email-error').text(data?.message || 'Registration failed. Please try again.');
                }
            }
        });
    });
});
</script>
@endpush
