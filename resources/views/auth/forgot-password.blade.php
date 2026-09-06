<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Forgot Password - SchoolOS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="schoolos-public min-h-screen bg-schoolos-soft text-[#171717] antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-8">
        <section class="w-full max-w-md rounded-lg border border-green-100 bg-white p-5 shadow-sm sm:p-8">
            <div class="flex items-center justify-between gap-4">
                <x-schoolos-logo class="w-max" />
                <x-theme-toggle />
            </div>
            <div class="mt-8 text-center">
                <h1 class="text-3xl font-bold text-gray-950">Forgot Password?</h1>
                <p class="mt-2 text-sm text-gray-600">Enter your email and we'll send you a password reset link.</p>
            </div>

            @if (session('status'))
                <div class="mt-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-5" data-loading-text="Sending...">
                @csrf
                <div>
                    <label for="email" class="text-sm font-semibold text-gray-900">Email Address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="Enter your email" class="mt-2 min-h-12 w-full rounded-md border border-gray-200 bg-white px-3 py-3 text-sm text-gray-950 shadow-sm outline-none transition focus:border-green-500 focus:ring-4 focus:ring-green-100" required>
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="min-h-12 w-full rounded-md bg-green-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-100 disabled:cursor-not-allowed disabled:opacity-70" data-submit-button>Send Reset Link</button>
                <a href="{{ route('login') }}" class="block text-center text-sm font-semibold text-green-700 hover:text-green-800">Back to login</a>
            </form>
        </section>
    </main>
</body>
</html>
