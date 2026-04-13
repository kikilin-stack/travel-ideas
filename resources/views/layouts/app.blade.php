<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '旅行想法') - Travel Ideas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ file_exists(public_path('css/style.css')) ? filemtime(public_path('css/style.css')) : 1 }}">
    @stack('css')
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="{{ route('travel-ideas.index') }}" class="logo">旅行想法</a>
            <div class="nav-links">
                @auth
                    <a href="{{ route('travel-ideas.create') }}">发布想法</a>
                    <a href="{{ route('travel-ideas.index') }}">探索</a>
                    <span class="user-name">{{ Auth::user()->name }}</span>
                    <a href="{{ route('logout') }}" class="btn-logout">退出</a>
                @else
                    <a href="{{ route('login') }}">登录</a>
                    <a href="{{ route('register') }}">注册</a>
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
        <p>&copy; {{ date('Y') }} 旅行想法管理平台</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('js/main.js') }}"></script>
    @stack('js')
</body>
</html>
