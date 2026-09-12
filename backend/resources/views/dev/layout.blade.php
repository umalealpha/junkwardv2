<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'Dev Portal' }} — Alpha Direct V2</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; margin: 0; background: #0f1419; color: #e6edf3; }
    a { color: #58a6ff; text-decoration: none; }
    a:hover { text-decoration: underline; }
    header { background: #161b22; padding: 14px 24px; border-bottom: 1px solid #30363d; display: flex; align-items: center; justify-content: space-between; }
    header h1 { margin: 0; font-size: 18px; font-weight: 600; }
    header nav a { color: #8b949e; margin-left: 18px; font-size: 13px; }
    header nav a.active { color: #58a6ff; }
    main { max-width: 1400px; margin: 0 auto; padding: 24px; }
    h2 { border-bottom: 1px solid #30363d; padding-bottom: 6px; font-size: 20px; }
    .card { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
    .card h3 { margin: 0 0 4px 0; font-size: 15px; }
    .card p { margin: 0; color: #8b949e; font-size: 13px; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th, td { text-align: left; padding: 6px 10px; border-bottom: 1px solid #21262d; vertical-align: top; }
    th { background: #0d1117; font-weight: 600; color: #8b949e; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
    tr:hover td { background: rgba(88, 166, 255, 0.05); }
    code { background: #0d1117; padding: 1px 6px; border-radius: 3px; font-family: 'SF Mono', Menlo, monospace; font-size: 11px; color: #e6edf3; }
    .method { display: inline-block; padding: 1px 7px; border-radius: 3px; font-weight: 600; font-size: 10px; font-family: 'SF Mono', Menlo, monospace; letter-spacing: 0.5px; }
    .method-GET    { background: #1f3a1f; color: #7ee787; }
    .method-POST   { background: #3a2e1f; color: #f0c674; }
    .method-PUT    { background: #1f2e3a; color: #79c0ff; }
    .method-PATCH  { background: #3a1f3a; color: #d2a8ff; }
    .method-DELETE { background: #3a1f1f; color: #ffa198; }
    pre { background: #0d1117; border: 1px solid #30363d; border-radius: 6px; padding: 14px; overflow-x: auto; font-size: 11px; line-height: 1.55; }
    .muted { color: #6e7681; font-size: 12px; }
    input[type="number"], input[type="search"] { background: #0d1117; border: 1px solid #30363d; color: #e6edf3; padding: 6px 10px; border-radius: 4px; font-size: 12px; }
    button { background: #238636; color: #fff; border: 0; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 12px; }
    .toc { column-count: 4; column-gap: 20px; font-size: 12px; line-height: 1.7; }
    details { margin-bottom: 8px; }
    details summary { cursor: pointer; padding: 6px 10px; background: #0d1117; border-radius: 4px; font-size: 13px; }
</style>
</head>
<body>
<header>
    <h1>Dev Portal — Alpha Direct V2</h1>
    <nav>
        <a href="{{ url('/dev') }}" class="{{ request()->is('dev') ? 'active' : '' }}">Home</a>
        <a href="{{ url('/dev/swagger') }}" class="{{ request()->is('dev/swagger') ? 'active' : '' }}">Swagger</a>
        <a href="{{ url('/dev/endpoints') }}" class="{{ request()->is('dev/endpoints') ? 'active' : '' }}">Endpoints</a>
        <a href="{{ url('/dev/openapi') }}">OpenAPI</a>
        <a href="{{ url('/dev/logs') }}" class="{{ request()->is('dev/logs') ? 'active' : '' }}">Logs</a>
        <a href="{{ url('/dev/docs') }}" class="{{ request()->is('dev/docs*') ? 'active' : '' }}">Docs</a>
    </nav>
</header>
<main>
    {{ $slot ?? '' }}
    @yield('content')
</main>
</body>
</html>
