<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Settings</title>
    <style>
        :root { color-scheme: dark; }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; background: #0b1020; color: #e5e7eb; }
        .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; }
        .card { width: min(92vw, 520px); border: 1px solid #243043; border-radius: 12px; background: #0b1222; padding: 24px; }
        .title { font-size: 16px; font-weight: 600; margin-bottom: 16px; }
        label { font-size: 12px; color: #9ca3af; }
        input[type="password"] { width: 100%; background: #111827; border: 1px solid #243043; border-radius: 8px; padding: 10px 12px; color: #e5e7eb; }
        .field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
        .muted { color: #9ca3af; font-size: 12px; }
        .error { color: #fca5a5; font-size: 12px; margin-top: 6px; }
        .success { color: #86efac; font-size: 12px; margin-bottom: 12px; }
        button { width: 100%; background: #2563eb; border: 1px solid #1d4ed8; border-radius: 8px; padding: 10px 14px; color: white; font-weight: 600; cursor: pointer; }
        a { color: #93c5fd; text-decoration: none; }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .sep { height: 1px; background: #243043; margin: 12px 0; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="top">
            <div class="title">Admin Settings</div>
            <div class="muted"><a href="{{ route('chart') }}">Back to chart</a></div>
        </div>

        @if (session('status'))
            <div class="success">{{ session('status') }}</div>
        @endif

        <div class="muted">Signed in as: <strong>{{ auth()->user()->email }}</strong></div>
        <div class="sep"></div>

        <form method="POST" action="{{ route('admin.password.update') }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label for="current_password">Current password</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" required />
                @error('current_password')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label for="password">New password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required />
                @error('password')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required />
            </div>

            <button type="submit">Update password</button>
        </form>

        <div class="sep"></div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" style="background:#334155;border-color:#1f2937;">Logout</button>
        </form>
    </div>
</div>
</body>
</html>
