<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sign in - SchoolOS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="schoolos-public min-h-screen bg-schoolos-soft text-[#171717] antialiased">
@php
    $fieldClass = 'mt-2 min-h-12 w-full rounded-md border border-gray-200 bg-white px-3 py-3 text-sm text-gray-950 shadow-sm outline-none transition focus:border-green-500 focus:ring-4 focus:ring-green-100';
    $demoAccounts = [
        'Super Admin' => 'superadmin@schoolos.test',
        'School Admin' => 'schooladmin@schoolos.test',
        'Teacher' => 'teacher@schoolos.test',
        'Student' => 'student@schoolos.test',
        'Parent' => 'parent@schoolos.test',
        'Accountant' => 'accountant@schoolos.test',
        'Librarian' => 'librarian@schoolos.test',
        'Receptionist' => 'receptionist@schoolos.test',
        'Staff' => 'staff@schoolos.test',
    ];
@endphp

<main class="grid min-h-screen lg:grid-cols-[0.95fr_1.05fr]">
    <section class="hidden bg-green-950 px-10 py-8 text-white lg:flex lg:flex-col lg:justify-between">
        <x-schoolos-logo :on-dark="true" />
        <div class="max-w-md">
            <h2 class="text-4xl font-bold leading-tight">Welcome back to SchoolOS</h2>
            <p class="mt-4 text-base leading-7 text-green-50">One secure sign-in for school admins, teachers, students, parents, and staff.</p>
            <div class="mt-8 rounded-lg border border-white/10 bg-white/10 p-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-md bg-green-500 text-white">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19V9.5L12 4l8 5.5V19M8 19v-7h8v7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold">Secure. Reliable. Built for Schools.</p>
                        <p class="mt-1 text-sm text-green-100">Access follows your existing SchoolOS role.</p>
                    </div>
                </div>
            </div>
        </div>
        <p class="text-sm text-green-100">SchoolOS</p>
    </section>

    <section class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6">
        <div class="w-full max-w-5xl lg:grid lg:grid-cols-[minmax(0,460px)_minmax(300px,360px)] lg:gap-6">
            <div class="rounded-lg border border-green-100 bg-white p-5 shadow-sm sm:p-8">
                <div class="text-center lg:text-left">
                    <div class="flex items-center justify-between gap-4">
                        <x-schoolos-logo class="w-max" />
                        <x-theme-toggle />
                    </div>
                    <h1 class="mt-8 text-3xl font-bold text-gray-950">Welcome back! 👋</h1>
                    <p class="mt-2 text-sm text-gray-600">Sign in to your SchoolOS account</p>
                </div>

                @if (session('status'))
                    <div class="mt-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first('identifier') ?: $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" id="login-form" class="mt-7 space-y-5" data-loading-text="Signing in...">
                    @csrf
                    <div>
                        <label for="identifier" class="text-sm font-semibold text-gray-900">Email Address</label>
                        <input id="identifier" name="identifier" type="text" value="{{ old('identifier') }}" placeholder="Enter your email" class="{{ $fieldClass }}" required autofocus aria-describedby="@error('identifier') identifier_error @enderror">
                        @error('identifier') <p id="identifier_error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password" class="text-sm font-semibold text-gray-900">Password</label>
                        <div class="mt-2 flex min-h-12 rounded-md border border-gray-200 bg-white shadow-sm focus-within:border-green-500 focus-within:ring-4 focus-within:ring-green-100">
                            <input id="password" name="password" type="password" placeholder="Enter your password" class="min-w-0 flex-1 rounded-l-md px-3 py-3 text-sm outline-none" required data-password-input>
                            <button type="button" class="px-3 text-sm font-semibold text-green-700" data-password-toggle aria-controls="password">Show</button>
                        </div>
                        @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500">
                            <span>Remember me</span>
                        </label>
                        <a href="{{ route('password.request') }}" class="text-sm font-semibold text-green-700 hover:text-green-800">Forgot password?</a>
                    </div>

                    <button type="submit" class="min-h-12 w-full rounded-md bg-green-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-100 disabled:cursor-not-allowed disabled:opacity-70" data-submit-button>Sign In</button>

                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span class="h-px flex-1 bg-gray-200"></span>
                        <span>or continue with</span>
                        <span class="h-px flex-1 bg-gray-200"></span>
                    </div>

                    <p class="text-center text-sm text-gray-600">Don't have an account? Contact your school admin.</p>
                </form>
            </div>

            <aside class="mt-5 rounded-lg border border-green-100 bg-white p-5 shadow-sm lg:mt-0">
                <h2 class="text-sm font-semibold text-gray-950">Demo logins</h2>
                <p class="mt-1 text-sm text-gray-600">For testing only. Click a role to fill the form.</p>
                <ul class="mt-4 max-h-[420px] space-y-2 overflow-y-auto pr-1">
                    @foreach ($demoAccounts as $role => $email)
                        <li class="flex items-center justify-between gap-3 rounded-md border border-gray-200 px-3 py-2">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-gray-900">{{ $role }}</span>
                                <span class="block truncate text-xs text-gray-500">{{ $email }}</span>
                            </span>
                            <button type="button" class="rounded-md bg-green-50 px-3 py-2 text-xs font-semibold text-green-700 hover:bg-green-100" data-email="{{ $email }}">Use</button>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">Password for all demo accounts: <code class="font-semibold text-gray-950">password</code></p>
            </aside>
        </div>
    </section>
</main>

<script>
    document.querySelectorAll('[data-email]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('identifier').value = btn.dataset.email;
            document.getElementById('password').value = 'password';
        });
    });
</script>
</body>
</html>
