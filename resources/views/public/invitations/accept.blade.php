<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Accept invitation — SchoolOS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f5f7; margin: 0; padding: 2rem 1rem; }
        .card { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); width: 100%; max-width: 400px; margin: 0 auto; }
        h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
        p.sub { color: #666; font-size: .875rem; margin: 0 0 1.25rem; }
        label { display: block; font-size: .875rem; margin-bottom: .25rem; color: #333; }
        input { width: 100%; padding: .5rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: .6rem; background: #2563eb; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        .error { color: #b91c1c; font-size: .875rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>You're invited to SchoolOS</h1>
        <p class="sub">Choose a password to activate your account.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('public.invitations.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required minlength="8" autofocus>

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8">

            <button type="submit">Activate account</button>
        </form>
    </div>
</body>
</html>
