<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Travel Ideas') - Travel Ideas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ file_exists(public_path('css/style.css')) ? filemtime(public_path('css/style.css')) : 1 }}">
    @stack('css')
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="{{ route('travel-ideas.index') }}" class="logo">Travel Ideas</a>
            <div class="nav-links">
                @auth
                    <a href="{{ route('travel-ideas.create') }}">Create Idea</a>
                    <a href="{{ route('travel-ideas.index') }}">Explore</a>
                    <span class="user-name">{{ Auth::user()->name }}</span>
                    <a href="{{ route('logout') }}" class="btn-logout">Log out</a>
                @else
                    <a href="{{ route('login') }}">Log in</a>
                    <a href="{{ route('register') }}">Sign up</a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="main-content">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-error">
                @foreach($errors->all() as $err) {{ $err }} @endforeach
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="footer">
        <p>&copy; {{ date('Y') }} Travel Ideas Platform</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('js/main.js') }}"></script>
    @stack('js')
</body>
</html>
