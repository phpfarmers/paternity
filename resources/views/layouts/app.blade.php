<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <!-- Styles -->
    <link href="{{ asset('css/layui-v2.2.5/layui.css')}}" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <script src="{{ asset('js/layui-v2.2.5/layui.js') }}"></script>
</head>
<body>
    <div id="app">
        <nav class="layui-layout layui-layout-admin">
            <div class="layui-header">
                <div class="layui-logo"><a href="/" style="color: #fff; text-decoration: none; font-weight: bold;">{{ config('app.name', '') }}</a></div>
                <ul class="layui-nav layui-layout-right">
                    <!-- Navigation Links -->
                </ul>
            </div>
        </nav>

        <main class="layui-main">
            @yield('content')
        </main>
    </div>

    <!-- Scripts -->
</body>
</html>