<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') — SchoolOS</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="flex h-full flex-col items-center justify-center px-6 text-center">
    <p class="text-sm font-semibold text-indigo-600">@yield('code')</p>
    <h1 class="mt-2 text-xl font-semibold text-gray-900">@yield('heading')</h1>
    <p class="mt-2 max-w-sm text-sm text-gray-500">@yield('message')</p>
    <a href="{{ url('/') }}" class="mt-6 inline-flex items-center rounded-md bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-500">
        Go to SchoolOS
    </a>
</body>
</html>
