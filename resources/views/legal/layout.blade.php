<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <title>@yield('title') — Lumen</title>
    <style>
        :root {
            --bg: #0B0B0E; --text: #F2F2F5; --muted: #A3A3B0; --accent: #E8B54D;
            --border: #34343F; --surface: #16161B;
        }
        @media (prefers-color-scheme: light) {
            :root { --bg: #FBFAF7; --text: #1B1B1F; --muted: #5C5C66; --accent: #9A6B12; --border: #E2E0DA; --surface: #F2F0EA; }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--text);
            font: 17px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
            -webkit-text-size-adjust: 100%;
        }
        main { max-width: 680px; margin: 0 auto; padding: 48px 20px 80px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .brand { font: 600 20px/1 ui-serif, "New York", Georgia, serif; color: var(--text); text-decoration: none; }
        .brand span { color: var(--accent); }
        nav a { color: var(--muted); text-decoration: none; font-size: 15px; margin-left: 16px; }
        nav a[aria-current] { color: var(--accent); }
        h1 { font: 600 36px/1.15 ui-serif, "New York", Georgia, serif; margin: 0 0 8px; letter-spacing: -0.01em; }
        h2 { font: 600 21px/1.3 ui-serif, "New York", Georgia, serif; margin: 40px 0 8px; }
        .meta { color: var(--muted); font-size: 15px; margin: 0 0 32px; }
        p, li { color: var(--text); }
        ul { padding-left: 22px; }
        li { margin: 6px 0; }
        a { color: var(--accent); }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 20px 22px; margin: 24px 0; }
        .card p { margin: 0; }
        footer { margin-top: 56px; padding-top: 20px; border-top: 1px solid var(--border); color: var(--muted); font-size: 14px; }
    </style>
</head>
<body>
<main>
    <header>
        <a class="brand" href="?lang={{ $lang }}">Lumen<span>.</span></a>
        <nav>
            <a href="{{ route('privacy', ['lang' => $lang]) }}" @if(request()->routeIs('privacy')) aria-current="page" @endif>{{ $lang === 'sk' ? 'Súkromie' : 'Privacy' }}</a>
            <a href="{{ route('support', ['lang' => $lang]) }}" @if(request()->routeIs('support')) aria-current="page" @endif>{{ $lang === 'sk' ? 'Podpora' : 'Support' }}</a>
            <a href="?lang={{ $lang === 'sk' ? 'en' : 'sk' }}" lang="{{ $lang === 'sk' ? 'en' : 'sk' }}">{{ $lang === 'sk' ? 'English' : 'Slovensky' }}</a>
        </nav>
    </header>
    @yield('content')
    <footer>© {{ date('Y') }} Martin Baláž</footer>
</main>
</body>
</html>
