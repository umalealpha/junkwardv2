{{--
    Shared shell for the three dead-end states of the cover-sheet flow
    (invalid link, expired session, document not available).

    Same one-page, no-external-assets approach as access.blade.php: the client
    is on a phone browser on mobile data. Each state says plainly what
    happened and gives the one number that can fix it.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} &mdash; Alpha Direct</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; background: #f4f6f9; color: #1a1a1a;
            font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 1.5;
        }
        .wrap { max-width: 460px; margin: 0 auto; padding: 20px 16px 48px; }
        .card { background: #fff; border: 1px solid #d4dde6; padding: 20px 18px; }
        .brand { font-size: 24px; font-weight: bold; color: #2e77c3; margin: 0 0 2px; }
        .brand span { color: #1a1a1a; }
        .sub { font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: #5a6875; margin: 0 0 18px; }
        .rule { border-top: 2px solid #2e77c3; margin: 0 0 16px; }
        h1 { font-size: 18px; margin: 0 0 10px; }
        p { margin: 0 0 12px; }
        .muted { color: #5a6875; font-size: 14px; }
        a.btn {
            display: block; text-align: center; text-decoration: none;
            font-size: 16px; font-weight: bold; color: #fff; background: #2e77c3;
            padding: 14px 16px; margin: 0 0 10px;
        }
        .foot { font-size: 12px; color: #5a6875; text-align: center; margin: 18px 0 0; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <p class="brand">alpha<span>direct</span></p>
        <p class="sub">Insurance Company (Pty) Ltd</p>
        <div class="rule"></div>
        <h1>{{ $title }}</h1>
        {!! $body !!}
        <p class="foot">Alpha Direct Insurance Co. (Pty) Ltd. &middot; Licensed by NBFIRA</p>
    </div>
</div>
</body>
</html>
