<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login</title>
    <style>
        :root { color-scheme: dark; }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; background: #0b1020; color: #e5e7eb; }
        .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; }
        .card { width: min(92vw, 420px); border: 1px solid #243043; border-radius: 12px; background: #0b1222; padding: 24px; }
        .title { font-size: 16px; font-weight: 600; margin-bottom: 16px; }
        label { font-size: 12px; color: #9ca3af; }
        input[type="email"], input[type="password"] { width: 100%; background: #111827; border: 1px solid #243043; border-radius: 8px; padding: 10px 12px; color: #e5e7eb; }
        input[type="checkbox"] { width: auto; }
        .field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
        .row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .row label { display: inline-flex; align-items: center; gap: 8px; }
        .muted { color: #9ca3af; font-size: 12px; }
        .error { color: #fca5a5; font-size: 12px; margin-top: 6px; }
        button { width: 100%; background: #2563eb; border: 1px solid #1d4ed8; border-radius: 8px; padding: 10px 14px; color: white; font-weight: 600; cursor: pointer; }
        a { color: #93c5fd; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="title">Sign in</div>

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required />
                @error('email')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required />
                @error('password')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <label class="muted">
                    <input type="checkbox" name="remember" value="1" />
                    Remember me
                </label>

                <span class="muted">
                    Use an existing admin account.
                </span>
            </div>

            <button type="submit">Login</button>
        </form>
    </div>
</div>
</body>
</html>
