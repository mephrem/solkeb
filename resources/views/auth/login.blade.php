<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="{{ asset('images/solkeb-icon-64.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/solkeb-icon-180.png') }}">
    <title>Log in · {{ \App\Models\Setting::get('hotel_name', 'Solkeb Hotel') }}</title>
    <link rel="stylesheet" href="{{ asset('css/solkeb.css') }}?v=17">
</head>
<body>
<div class="login-page">
    <form class="login-card" method="POST" action="{{ url('/login') }}">
        @csrf
        <img class="login-logo" src="{{ asset('images/solkeb-logo.png') }}" alt="{{ \App\Models\Setting::get('hotel_name', 'Solkeb Hotel') }}">
        <div class="login-tagline">{{ \App\Models\Setting::get('tagline', 'Peaceful and Comfortable!') }}</div>
        <div class="brand-sub">{{ \App\Models\Setting::get('city', 'Jijiga') }} · Staff login</div>

        @error('email')<div class="alert alert-error">{{ $message }}</div>@enderror

        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="field">
            <label class="check"><input type="checkbox" name="remember" value="1"> Keep me logged in</label>
        </div>
        <button class="btn" type="submit">Log in</button>
    </form>
</div>
</body>
</html>
