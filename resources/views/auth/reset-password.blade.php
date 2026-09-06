<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reset Password - SchoolOS</title>
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
                <h1 class="text-3xl font-bold text-gray-950">Reset Password</h1>
                <p class="mt-2 text-sm text-gray-600">Create a new password for your SchoolOS account.</p>
            </div>

            @if ($errors->any())
                <div class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-5" data-loading-text="Updating...">
                @csrf
                <input type="hidden" name="token" value="{{ old('token', $token) }}">
                <div>
                    <label for="email" class="text-sm font-semibold text-gray-900">Email Address</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" class="mt-2 min-h-12 w-full rounded-md border border-gray-200 bg-white px-3 py-3 text-sm text-gray-950 shadow-sm outline-none transition focus:border-green-500 focus:ring-4 focus:ring-green-100" required>
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password" class="text-sm font-semibold text-gray-900">Password</label>
                    <div class="mt-2 flex min-h-12 rounded-md border border-gray-200 bg-white shadow-sm focus-within:border-green-500 focus-within:ring-4 focus-within:ring-green-100">
                        <input id="password" name="password" type="password" placeholder="Create password" class="min-w-0 flex-1 rounded-l-md px-3 py-3 text-sm outline-none" required minlength="8" data-password-input>
                        <button type="button" class="px-3 text-sm font-semibold text-green-700" data-password-toggle aria-controls="password">Show</button>
                    </div>
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="text-sm font-semibold text-gray-900">Confirm Password</label>
                    <div class="mt-2 flex min-h-12 rounded-md border border-gray-200 bg-white shadow-sm focus-within:border-green-500 focus-within:ring-4 focus-within:ring-green-100">
                        <input id="password_confirmation" name="password_confirmation" type="password" placeholder="Confirm password" class="min-w-0 flex-1 rounded-l-md px-3 py-3 text-sm outline-none" required minlength="8" data-password-input>
                        <button type="button" class="px-3 text-sm font-semibold text-green-700" data-password-toggle aria-controls="password_confirmation">Show</button>
                    </div>
                </div>
                <button type="submit" class="min-h-12 w-full rounded-md bg-green-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-100 disabled:cursor-not-allowed disabled:opacity-70" data-submit-button>Reset Password</button>
                <a href="{{ route('login') }}" class="block text-center text-sm font-semibold text-green-700 hover:text-green-800">Back to login</a>
            </form>
        </section>
    </main>
</body>
</html>
