{{--
    Cover-sheet document access — what the client sees after scanning the QR.

    Nothing personal is rendered here: only the policy number (already printed
    on the sheet in their hand) and a masked hint of the number the code goes
    to. The insured's name, address and the document itself appear only after
    verification.

    Kept to one small page with no external assets: the client is on a phone
    browser on Botswana mobile data, straight out of a camera app.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Your Policy Document &mdash; Alpha Direct</title>
    <style>
        :root {
            --blue: #2e77c3;
            --ink: #1a1a1a;
            --muted: #5a6875;
            --line: #d4dde6;
            --green: #e5f4e3;
            --peach: #ecd5c2;
            --bad: #b3261e;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background: #f4f6f9;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 16px;
            line-height: 1.5;
        }
        .wrap { max-width: 460px; margin: 0 auto; padding: 20px 16px 48px; }
        .card { background: #fff; border: 1px solid var(--line); padding: 20px 18px; }
        .brand { font-size: 24px; font-weight: bold; color: var(--blue); margin: 0 0 2px; }
        .brand span { color: var(--ink); }
        .sub { font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); margin: 0 0 18px; }
        .rule { border-top: 2px solid var(--blue); margin: 0 0 16px; }
        .label { font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: var(--blue); margin: 0 0 3px; }
        .polno { font-size: 19px; font-weight: bold; margin: 0 0 18px; word-break: break-all; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        p { margin: 0 0 12px; }
        .muted { color: var(--muted); font-size: 14px; }
        button {
            display: block;
            width: 100%;
            font-family: inherit;
            font-size: 16px;
            font-weight: bold;
            color: #fff;
            background: var(--blue);
            border: none;
            padding: 14px 16px;
            cursor: pointer;
            margin: 0 0 10px;
        }
        button.secondary { background: #fff; color: var(--blue); border: 1px solid var(--blue); }
        button:disabled { opacity: .55; cursor: not-allowed; }
        input[type="tel"] {
            width: 100%;
            font-family: inherit;
            font-size: 26px;
            letter-spacing: 8px;
            text-align: center;
            padding: 12px;
            border: 1px solid var(--line);
            margin: 0 0 12px;
        }
        .note { background: var(--green); border: 1px solid var(--blue); padding: 10px 12px; font-size: 13px; margin: 0 0 14px; }
        .bar { background: var(--peach); padding: 8px 12px; font-size: 12px; font-weight: bold; text-align: center; margin: 0 0 16px; }
        .msg { font-size: 14px; padding: 10px 12px; margin: 0 0 12px; }
        .msg.err { background: #fdecea; border: 1px solid var(--bad); color: var(--bad); }
        .msg.ok { background: var(--green); border: 1px solid var(--blue); }
        .foot { font-size: 12px; color: var(--muted); text-align: center; margin: 18px 0 0; }
        /* Staging-only OTP display. Loud on purpose — nobody should mistake
           this page for the production one. */
        .dev {
            margin: 14px 0 0; padding: 10px 12px; font-size: 13px;
            background: #fff8e1; border: 1px dashed #b58100; color: #6b4e00;
        }
        .dev-code {
            display: block; font-size: 30px; font-weight: bold; letter-spacing: 8px;
            text-align: center; margin: 8px 0 6px; color: #1a1a1a;
        }
        button.dev-fill { margin: 0; font-size: 14px; padding: 9px 12px; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <p class="brand">alpha<span>direct</span></p>
        <p class="sub">Insurance Company (Pty) Ltd</p>
        <div class="rule"></div>

        <p class="bar">YOUR FULL POLICY DOCUMENT</p>

        <p class="label">Policy number</p>
        <p class="polno">{{ $policyNumber }}</p>

        {{-- Step 1: choose how to verify --}}
        <div id="step-start">
            <h1>First, we need to check it's you</h1>
            <p class="muted">Your policy document contains your personal details, so we do not open it from the
                QR code alone.</p>

            @if ($signedInOwner)
                <button type="button" id="btn-login">Continue &mdash; I'm signed in</button>
                <button type="button" id="btn-send" class="secondary">Send me a code instead</button>
            @else
                <button type="button" id="btn-send">Send me a one-time code</button>
                @if ($cellHint)
                    <p class="muted">We will send it to {{ $cellHint }} &mdash; the mobile number on your policy.</p>
                @else
                    <p class="muted">We will send it to the mobile number on your policy.</p>
                @endif
            @endif
        </div>

        {{-- Step 2: enter the code --}}
        <div id="step-code" hidden>
            <h1>Enter your code</h1>
            <p class="muted" id="sent-to"></p>
            <input type="tel" id="code" inputmode="numeric" autocomplete="one-time-code"
                   maxlength="6" pattern="[0-9]*" placeholder="000000" aria-label="Six digit code">
            <button type="button" id="btn-verify">Open my document</button>
            <button type="button" id="btn-resend" class="secondary">Send a new code</button>

            {{--
                Staging only. The server returns devCode as null in production
                (gated on APP_STATUS, not APP_ENV — APP_ENV is 'local' even in
                prod), so on the live estate this block never has anything to
                render and the client only ever gets the code by SMS.
            --}}
            <div id="dev-box" class="dev" hidden>
                <strong>Staging only</strong> &mdash; no SMS is sent to test numbers here.
                <span id="dev-code" class="dev-code"></span>
                <button type="button" id="btn-dev-fill" class="secondary dev-fill">Use this code</button>
            </div>
        </div>

        {{-- Step 3: download --}}
        <div id="step-done" hidden>
            <h1>Your document is ready</h1>
            <p class="note">Your policy document will save to your phone. You can open it any time, or send it
                to a printer from your phone.</p>
            <button type="button" id="btn-download">Save my policy document</button>
            <p class="muted">If nothing happens, tap the button again.</p>
        </div>

        <div id="msg" class="msg" hidden role="status" aria-live="polite"></div>

        <p class="foot">Need help? Call +267 392 8264</p>
    </div>
</div>

<script>
(function () {
    var token   = @json($token);
    var csrf    = document.querySelector('meta[name="csrf-token"]').content;
    var downloadUrl = null;

    var el = {
        start:    document.getElementById('step-start'),
        code:     document.getElementById('step-code'),
        done:     document.getElementById('step-done'),
        msg:      document.getElementById('msg'),
        sentTo:   document.getElementById('sent-to'),
        codeIn:   document.getElementById('code'),
        send:     document.getElementById('btn-send'),
        login:    document.getElementById('btn-login'),
        verify:   document.getElementById('btn-verify'),
        resend:   document.getElementById('btn-resend'),
        download: document.getElementById('btn-download'),
        devBox:   document.getElementById('dev-box'),
        devCode:  document.getElementById('dev-code'),
        devFill:  document.getElementById('btn-dev-fill')
    };

    function say(text, kind) {
        if (!text) { el.msg.hidden = true; return; }
        el.msg.textContent = text;
        el.msg.className = 'msg ' + (kind === 'ok' ? 'ok' : 'err');
        el.msg.hidden = false;
    }

    function step(name) {
        el.start.hidden = name !== 'start';
        el.code.hidden  = name !== 'code';
        el.done.hidden  = name !== 'done';
    }

    function busy(button, on, idleText) {
        if (!button) return;
        button.disabled = on;
        if (on) { button.dataset.idle = button.textContent; button.textContent = 'Please wait…'; }
        else { button.textContent = idleText || button.dataset.idle || button.textContent; }
    }

    function post(path, body) {
        return fetch('/p/d/' + encodeURIComponent(token) + path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(body || {})
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (d) { return { status: r.status, data: d }; });
        });
    }

    function sendCode(button) {
        say(null);
        busy(button, true);
        post('/otp/send', {}).then(function (res) {
            busy(button, false);
            if (!res.data.ok) {
                say(res.data.message || 'We could not send your code. Please call us on +267 392 8264.');
                return;
            }
            el.sentTo.textContent = res.data.sentTo
                ? 'We sent a 6-digit code to ' + res.data.sentTo + '. It is valid for 5 minutes.'
                : 'We sent a 6-digit code to the mobile number on your policy. It is valid for 5 minutes.';
            step('code');

            // Staging: the code comes back in the response so a tester does
            // not need a real handset. Production returns null here.
            if (res.data.devCode) {
                el.devCode.textContent = res.data.devCode;
                el.devBox.hidden = false;
            } else {
                el.devBox.hidden = true;
            }

            el.codeIn.focus();
        }).catch(function () {
            busy(button, false);
            say('No connection. Check your data and try again.');
        });
    }

    function openDocument() {
        // Navigating rather than fetching: the browser's own download
        // handling is what saves the PDF to the phone. The URL is a signed,
        // short-lived one the server minted after verification.
        if (downloadUrl) window.location.href = downloadUrl;
    }

    if (el.send)  el.send.addEventListener('click', function () { sendCode(el.send); });
    if (el.resend) el.resend.addEventListener('click', function () { sendCode(el.resend); });

    if (el.login) {
        el.login.addEventListener('click', function () {
            say(null);
            busy(el.login, true);
            post('/login', {}).then(function (res) {
                busy(el.login, false);
                if (!res.data.ok || !res.data.downloadUrl) {
                    say(res.data.message || 'We could not verify your account. Use a one-time code instead.');
                    return;
                }
                downloadUrl = res.data.downloadUrl;
                step('done');
                openDocument();
            }).catch(function () {
                busy(el.login, false);
                say('No connection. Check your data and try again.');
            });
        });
    }

    if (el.verify) {
        el.verify.addEventListener('click', function () {
            var code = (el.codeIn.value || '').replace(/\D+/g, '');
            if (code.length !== 6) { say('Enter the 6-digit code we sent you.'); return; }
            say(null);
            busy(el.verify, true);
            post('/otp/verify', { code: code }).then(function (res) {
                busy(el.verify, false);
                if (!res.data.ok || !res.data.downloadUrl) {
                    say(res.data.message || 'That code is not right. Check it and try again.');
                    return;
                }
                downloadUrl = res.data.downloadUrl;
                step('done');
                openDocument();
            }).catch(function () {
                busy(el.verify, false);
                say('No connection. Check your data and try again.');
            });
        });
    }

    if (el.devFill) {
        el.devFill.addEventListener('click', function () {
            el.codeIn.value = (el.devCode.textContent || '').trim();
            el.codeIn.focus();
        });
    }

    if (el.download) el.download.addEventListener('click', openDocument);
})();
</script>
</body>
</html>
