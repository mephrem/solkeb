<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/solkeb-icon-64.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/solkeb-icon-180.png') }}">
    <title>@yield('title', 'Dashboard') · {{ \App\Models\Setting::get('hotel_name', 'Solkeb Hotel') }}</title>
    <link rel="stylesheet" href="{{ asset('css/solkeb.css') }}?v=17">
    <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}" defer></script>
    <script src="{{ asset('js/solkeb.js') }}?v=11" defer></script>
    <script src="{{ asset('js/datatable.js') }}?v=6" defer></script>
</head>
<body>
@php
    $user = auth()->user();
    $nav = [
        ['dashboard', 'Dashboard', 'dashboard', null, 'M3 12l9-8 9 8M5 10v10h5v-6h4v6h5V10'],
        ['rooms.index', 'Room board', 'rooms.*', null, 'M3 21V5a2 2 0 012-2h14a2 2 0 012 2v16M3 21h18M9 7h1m4 0h1M9 11h1m4 0h1M9 15h1m4 0h1'],
        ['reservations.index', 'Reservations', 'reservations.*', ['admin','manager','receptionist','accountant'], 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z'],
        ['guests.index', 'Guests', 'guests.*', ['admin','manager','receptionist','accountant'], 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8zm11 10v-2a4 4 0 00-3-3.87M16 2.13a4 4 0 010 7.75'],
        ['organizations.index', 'Organisations', 'organizations.*', ['admin','manager','receptionist','accountant'], 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1'],
        ['my-activity', 'My activity', 'my-activity', ['admin','manager','receptionist','accountant'], 'M9 11l3 3 8-8M20 12v7a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2h9'],
        ['reports.index', 'Reports', 'reports.*', ['admin','manager','accountant'], 'M4 20V10m6 10V4m6 16v-7m4 7H2'],
    ];
@endphp
<div class="topbar">
    <img src="{{ asset('images/solkeb-logo-white.png') }}" alt="{{ \App\Models\Setting::get('hotel_name', 'Solkeb Hotel') }}" height="26" style="height:26px;width:auto">
    <button type="button" data-menu-toggle aria-controls="sidebar" aria-expanded="false">Menu</button>
</div>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <a href="{{ route('dashboard') }}" class="brand-logo"><img src="{{ asset('images/solkeb-logo-white.png') }}" alt="{{ \App\Models\Setting::get('hotel_name', 'Solkeb Hotel') }}" width="192" height="36"></a>
            <div class="brand-tagline">{{ \App\Models\Setting::get('tagline', 'Peaceful and Comfortable!') }}</div>
            <div class="brand-sub">{{ \App\Models\Setting::get('city', 'Jijiga') }} · Hotel Management</div>
        </div>
        <nav class="nav">
            @foreach ($nav as [$route, $label, $pattern, $roles, $icon])
                @if (! $roles || $user->hasRole(...$roles))
                    <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        {{ $label }}
                    </a>
                @endif
            @endforeach
            @if ($user->hasRole('admin','manager'))
                <div class="nav-label">Setup</div>
                @foreach ([
                    ['setup.rooms', 'Rooms & rates', ['setup.rooms','room-types.*','room-setup.*','room-import.*'], 'M4 7h16M4 12h16M4 17h10'],
                    ['services.index', 'Services', ['services.*'], 'M12 8c-2.2 0-4 .9-4 2s1.8 2 4 2 4 .9 4 2-1.8 2-4 2m0-8V6m0 10v2'],
                ] as [$route, $label, $pattern, $icon])
                    <a href="{{ route($route) }}" class="{{ request()->routeIs(...$pattern) ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        {{ $label }}
                    </a>
                @endforeach
            @endif
            @if ($user->isAdmin())
                <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                    Staff accounts
                </a>
                <a href="{{ route('accounts.index') }}" class="{{ request()->routeIs('accounts.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11m16-11v11M8 14v3m4-3v3m4-3v3"/></svg>
                    Bank accounts
                </a>
                <a href="{{ route('settings.edit') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15a3 3 0 100-6 3 3 0 000 6z"/><path d="M19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/></svg>
                    Hotel settings
                </a>
            @endif
        </nav>
        <div class="user-box">
            <div><a href="{{ route('account.edit') }}" style="color:#fff"><strong>{{ $user->name }}</strong></a></div>
            <div style="opacity:.85">{{ ucfirst($user->role) }}</div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Log out</button></form>
        </div>
    </aside>

    <main class="main">
        {{-- Shortcuts on the dashboard and room board, placed on the title line below --}}
        @if (request()->routeIs('dashboard', 'rooms.index'))
        <div class="quickbar no-print" id="quickbar">
            @if ($user->hasRole('admin','manager','receptionist'))
                <a class="btn btn-sm" href="{{ route('reservations.create') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    New booking
                </a>
            @endif
            @if ($user->hasRole('admin','manager','receptionist','accountant'))
                <a class="btn btn-sm btn-light" href="{{ route('breakfast') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 010 8h-1M3 8h14v9a4 4 0 01-4 4H7a4 4 0 01-4-4V8zM6 1v3M10 1v3M14 1v3"/></svg>
                    Breakfast list
                </a>
            @endif
        </div>
        @endif
        @include('partials.flash')
        @yield('content')
        <script>
            // Put the shortcuts on the same line as the page title, after the page's own buttons (skip any the page already has)
            (function () {
                var bar = document.getElementById('quickbar'), head = document.querySelector('.main .page-head');
                if (!bar || !head) return;
                var box = head.querySelector(':scope > .actions');
                if (!box) { box = document.createElement('div'); box.className = 'actions'; head.appendChild(box); }
                box.classList.add('quick-in-head');
                Array.prototype.slice.call(bar.children).forEach(function (a) {
                    if (head.querySelector('a[href="' + a.getAttribute('href') + '"]')) { a.remove(); return; }
                    a.classList.add('no-print');
                    box.appendChild(a);
                });
                bar.remove();
            })();
        </script>
    </main>
</div>
</body>
</html>
