<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>API Docs (Swagger UI) — Alpha Direct V2</title>
<link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Ctext y='14' font-size='14'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">
<style>
    body { margin: 0; font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; }
    .topbar { background: #161b22; color: #e6edf3; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #30363d; position: sticky; top: 0; z-index: 10; }
    .topbar h1 { font-size: 16px; font-weight: 600; margin: 0; }
    .topbar .left { display: flex; align-items: center; gap: 16px; }
    .topbar .back { background: #238636; color: #fff; padding: 6px 12px; border-radius: 6px; font-size: 13px; text-decoration: none; font-weight: 500; }
    .topbar .back:hover { background: #2ea043; color: #fff; }
    .topbar a { color: #8b949e; text-decoration: none; font-size: 13px; }
    .topbar a:hover { color: #58a6ff; }
    .topbar .token-status { font-size: 12px; color: #8b949e; padding: 4px 10px; border-radius: 4px; background: #21262d; }
    .topbar .token-status.ok { color: #3fb950; background: rgba(63,185,80,0.1); }
    .topbar .token-status.err { color: #f85149; background: rgba(248,81,73,0.1); }
    /* Swagger UI theme tweaks so it doesn't look out of place */
    .swagger-ui .topbar { display: none; }
    #swagger-ui { max-width: 1400px; margin: 0 auto; padding: 0 16px; }
    /* Ash-out DELETE operations — visible but execution disabled below */
    .swagger-ui .opblock.opblock-delete { opacity: 0.7; }
    .swagger-ui .opblock.opblock-delete .opblock-summary::after {
        content: '🔒 execution disabled on dev portal';
        margin-left: 12px;
        font-size: 11px;
        color: #b35a00;
        background: rgba(255,171,64,0.15);
        padding: 2px 8px;
        border-radius: 3px;
    }
</style>
</head>
<body>
<div class="topbar">
    <div class="left">
        <a href="{{ url('/admin/dashboard') }}" class="back" title="Return to Graphite admin dashboard">← Back to Admin</a>
        <h1>🧭 Alpha Direct V2 — API Reference</h1>
    </div>
    <div>
        <span id="token-status" class="token-status">Minting session token…</span>
        <a href="{{ url('/dev') }}" style="margin-left: 16px;">Dev Home</a>
        <a href="{{ url('/dev/openapi?refresh=1') }}" style="margin-left: 16px;">Refresh Spec</a>
        <a href="{{ url('/dev/openapi') }}" target="_blank" rel="noopener" style="margin-left: 16px;">Raw JSON</a>
    </div>
</div>
<div id="swagger-ui"></div>

<script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js" crossorigin></script>
<script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-standalone-preset.js" crossorigin></script>
<script>
// Fetch a short-lived Sanctum token for the currently-logged-in admin so
// "Try it out" works without the operator hunting for a PAT. If the user
// isn't logged in we surface that clearly instead of landing them at a
// 401 for every request. Sessions here are server-side — same cookie as
// /admin — so withCredentials must be true for the mint call.
//
// We also cache the minted token in sessionStorage so a page refresh
// (eg. after clicking "Refresh Spec") doesn't mint a fresh one every
// time — cuts auth noise and makes persistAuthorization actually work.
async function mintDevToken() {
    const cached = sessionStorage.getItem('dev_portal_token');
    const cachedExp = sessionStorage.getItem('dev_portal_token_exp');
    if (cached && cachedExp && Date.parse(cachedExp) > Date.now() + 60000) {
        return cached;  // still good for >1 min
    }
    try {
        const r = await fetch("{{ url('/dev/swagger-token') }}", {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!r.ok) {
            sessionStorage.removeItem('dev_portal_token');
            sessionStorage.removeItem('dev_portal_token_exp');
            return null;
        }
        const d = await r.json();
        if (d.token) {
            sessionStorage.setItem('dev_portal_token', d.token);
            if (d.expires_at) sessionStorage.setItem('dev_portal_token_exp', d.expires_at);
        }
        return d.token || null;
    } catch (_) { return null; }
}

window.onload = async function () {
    const token = await mintDevToken();
    const statusEl = document.getElementById('token-status');
    if (token) {
        statusEl.textContent = 'Token attached — Try it out is authorised';
        statusEl.classList.add('ok');
    } else {
        // Fail-open with a helpful prompt: let the user paste their own
        // PAT if the auto-mint failed (eg. CORS + cookie issue on a new
        // env, or they're hitting this from a non-admin account).
        statusEl.innerHTML = '<span>No session token — </span>' +
            '<a href="#" id="paste-token-link" style="color:#58a6ff;text-decoration:underline;">paste a Bearer token</a>' +
            ' <span style="color:#8b949e;">or log in on /admin and refresh</span>';
        statusEl.classList.add('err');
        document.getElementById('paste-token-link').addEventListener('click', (e) => {
            e.preventDefault();
            const manual = window.prompt('Paste your Sanctum bearer token (the part after "Bearer "):');
            if (manual && manual.trim()) {
                sessionStorage.setItem('dev_portal_token', manual.trim());
                sessionStorage.setItem('dev_portal_token_exp', new Date(Date.now() + 2 * 3600 * 1000).toISOString());
                window.location.reload();
            }
        });
    }

    window.ui = SwaggerUIBundle({
        url: "{{ url('/dev/openapi') }}",
        dom_id: '#swagger-ui',
        deepLinking: true,
        // 'none' keeps every group/operation collapsed on load. 'list' was
        // expanding neighbouring operations on first click because the raw
        // spec tags many routes under the same top-level segment.
        docExpansion: 'none',
        defaultModelsExpandDepth: 0,
        displayRequestDuration: true,
        filter: true,
        tryItOutEnabled: true,
        // persistAuthorization=false: Swagger UI 5.17.x's js-cookie shim has a
        // broken binding for the bearer-scheme persist path, throwing
        //   "TypeError: o.get is not a function"
        // every time authActions.authorize fires. We mint a fresh token on
        // every page load via /dev/swagger-token anyway, so persisting auth
        // across reloads adds no value — only noise in the console.
        persistAuthorization: false,
        // DELETE execution is disabled in the dev portal — listing only.
        // Destructive calls must go through the real admin UI where they
        // carry proper audit + confirmation steps.
        supportedSubmitMethods: ['get', 'post', 'put', 'patch', 'head', 'options'],
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIStandalonePreset,
        ],
        plugins: [SwaggerUIBundle.plugins.DownloadUrl],
        layout: 'StandaloneLayout',
        requestInterceptor: (req) => {
            // Belt + braces: even if a dev fiddles the config, never let a
            // DELETE slip through from this portal.
            if ((req.method || '').toUpperCase() === 'DELETE') {
                throw new Error('DELETE is disabled on the dev portal — use the admin UI instead.');
            }
            // Auto-attach the minted token to every Try-it-out call. Users
            // can still override via the Authorize button for cross-account
            // testing.
            if (token && !req.headers['Authorization']) {
                req.headers['Authorization'] = 'Bearer ' + token;
            }
            // Swagger UI sends Accept: */* by default, which flips Laravel's
            // expectsJson() to false for unauthenticated calls and lands us
            // at an HTML redirect. Force JSON so 401 comes back as JSON.
            req.headers['Accept'] = 'application/json';
            return req;
        },
        onComplete: () => {
            // Pre-authorise the Sanctum security scheme so users don't have
            // to open the Authorize modal every page-load.
            //
            // IMPORTANT: do NOT call preauthorizeApiKey here. That helper is
            // for OpenAPI `apiKey` auth schemes; Sanctum is `http/bearer`.
            // Calling preauthorizeApiKey on a bearer scheme triggers Swagger
            // UI 5.x's cookie-persist branch which throws
            //   "TypeError: o.get is not a function"
            // (its js-cookie shim doesn't bind .get when the scheme isn't
            // apiKey). authActions.authorize() with the correct schema shape
            // is the right entrypoint for bearer auth and persists fine.
            if (token && window.ui && window.ui.authActions) {
                try {
                    window.ui.authActions.authorize({
                        sanctumToken: {
                            name: 'sanctumToken',
                            schema: { type: 'http', scheme: 'bearer' },
                            value: token,
                        },
                    });
                } catch (e) {
                    console.warn('Swagger pre-auth failed:', e?.message || e);
                }
            }
        },
    });
};
</script>
</body>
</html>
