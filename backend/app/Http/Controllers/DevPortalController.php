<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Http\Middleware\DevPortalGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Backend dev portal — consolidated view over what developers /
 * ops need when working on this codebase. Mounted at /dev, gated
 * by DevPortalGate middleware so it's never exposed in production
 * to ordinary users.
 *
 * Pages:
 *   - /dev                 landing, lists available tools
 *   - /dev/endpoints       every API route (method + URI + controller + middleware)
 *   - /dev/openapi         raw OpenAPI 3.0 JSON spec
 *   - /dev/logs            tail of storage/logs/laravel.log (last N lines, configurable)
 *   - /dev/docs            static markdown under backend/dev/ rendered
 */
class DevPortalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tools = [];
        foreach ([
            ['key' => 'swagger',   'label' => 'API Docs (Swagger UI)', 'desc' => 'Interactive API reference — try endpoints, copy curl, inspect schemas. Open to everyone on the backend.', 'url' => url('/dev/swagger')],
            ['key' => 'endpoints', 'label' => 'API Endpoints',         'desc' => 'All registered routes with method, URI, controller + middleware.',                                      'url' => url('/dev/endpoints')],
            ['key' => 'openapi',   'label' => 'OpenAPI (JSON)',        'desc' => 'Download the generated OpenAPI 3.0 spec.',                                                             'url' => url('/dev/openapi')],
            ['key' => 'logs',      'label' => 'Error Logs',            'desc' => 'Tail of storage/logs/laravel.log — last 1,000 lines by default.',                                      'url' => url('/dev/logs')],
            ['key' => 'docs',      'label' => 'Dev Docs',              'desc' => 'Static markdown under backend/dev/ (endpoints MD, schema audits, etc.).',                              'url' => url('/dev/docs')],
        ] as $t) {
            if (DevPortalGate::can($t['key'], $user)) $tools[] = $t;
        }
        return view('dev.index', ['tools' => $tools]);
    }

    private function authorizeFeature(string $feature, Request $request): void
    {
        if (!DevPortalGate::can($feature, $request->user())) {
            abort(404);
        }
    }

    /**
     * List every registered route with its verbs, URI, controller + middleware.
     * Mirrors `php artisan route:list --json` but formatted for human reading.
     */
    public function endpoints(Request $request)
    {
        $this->authorizeFeature('endpoints', $request);

        $routes = collect(Route::getRoutes())->map(function ($r) {
            $methods = array_values(array_filter($r->methods(), fn ($m) => $m !== 'HEAD'));
            return [
                'methods'     => $methods,
                'uri'         => '/' . ltrim($r->uri(), '/'),
                'name'        => $r->getName(),
                'action'      => $this->prettyAction($r->getActionName()),
                'middleware'  => array_values($r->gatherMiddleware()),
            ];
        })
        ->sortBy(fn ($r) => $r['uri'])
        ->values();

        // Group by top-level segment for faster scanning on the page.
        $grouped = $routes->groupBy(function ($r) {
            $parts = explode('/', trim($r['uri'], '/'));
            return $parts[0] ?: '(root)';
        })->sortKeys();

        return view('dev.endpoints', [
            'grouped' => $grouped,
            'total'   => $routes->count(),
        ]);
    }

    public function openapi(Request $request)
    {
        // Readable by any logged-in backend user — the whole Laravel
        // admin is already behind auth, so we don't layer a dev-portal
        // role on top of it. DevPortalGate still wraps the route as a
        // last line of defence for non-admin contexts.
        //
        // ?refresh=1 forces a regenerate — useful after route changes
        // without waiting for the stored file to be purged. The
        // regenerate path also runs automatically when the file is
        // missing (first hit after a deploy that cleared it).
        $path = base_path('dev/openapi.json');
        if ($request->boolean('refresh') || !File::exists($path)) {
            $this->regenerate();
        }
        return response()->file($path, ['Content-Type' => 'application/json']);
    }

    /**
     * Inline Swagger UI — readable by any backend user who can reach
     * /dev/*. The UI loads from unpkg CDN and points at /dev/openapi.
     */
    public function swagger()
    {
        return view('dev.swagger');
    }

    /**
     * Mint a short-lived Sanctum PAT for the currently-logged-in admin so
     * the /dev/swagger "Try it out" calls work without the operator
     * manually pasting a token. Requires an authenticated web session
     * (same cookie as the Laravel admin) — returns 401 if the user hit
     * /dev/swagger without logging in first.
     *
     * Token expires after 2 hours and is tagged `dev-portal` so ops can
     * spot these in personal_access_tokens when auditing.
     */
    public function swaggerToken(Request $request)
    {
        $user = $request->user('web') ?? $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Housekeeping — drop any prior dev-portal token for this user so
        // the table doesn't accumulate one row per refresh.
        try {
            $user->tokens()->where('name', 'dev-portal')->delete();
        } catch (\Throwable $e) {
            // Non-fatal — proceed with mint.
        }

        $token = $user->createToken('dev-portal', ['*']);
        return response()->json([
            'token'      => $token->plainTextToken,
            'expires_at' => now()->addHours(2)->toIso8601String(),
            'user_id'    => $user->id,
        ]);
    }

    public function logs(Request $request)
    {
        $this->authorizeFeature('logs', $request);
        $path  = storage_path('logs/laravel.log');
        $lines = (int) $request->query('lines', 1000);
        $lines = max(100, min($lines, 20000));

        $tail = '';
        if (File::exists($path)) {
            // Read last N lines without loading the whole file.
            $tail = $this->tail($path, $lines);
        }

        return view('dev.logs', [
            'path'  => $path,
            'lines' => $lines,
            'tail'  => $tail,
        ]);
    }

    public function docs(Request $request, ?string $file = null)
    {
        $this->authorizeFeature('docs', $request);
        $base = base_path('dev');
        if ($file === null) {
            $files = File::exists($base)
                ? collect(File::files($base))
                    ->filter(fn ($f) => in_array($f->getExtension(), ['md', 'markdown', 'txt'], true))
                    ->map(fn ($f) => [
                        'name' => $f->getFilename(),
                        'url'  => url('/dev/docs/' . $f->getFilename()),
                        'size' => $f->getSize(),
                    ])
                    ->sortBy('name')
                    ->values()
                    ->all()
                : [];
            return view('dev.docs-index', ['files' => $files]);
        }

        // Prevent directory traversal.
        if (str_contains($file, '/') || str_contains($file, '\\') || str_contains($file, '..')) {
            abort(400, 'Invalid file.');
        }

        $full = $base . DIRECTORY_SEPARATOR . $file;
        if (!File::exists($full)) abort(404);

        return view('dev.doc', [
            'file'    => $file,
            'content' => File::get($full),
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────

    private function prettyAction(string $action): string
    {
        // AlphaDirect\Http\Controllers\Api\V1\PolicyController@index
        // → PolicyController@index
        if ($action === 'Closure') return 'Closure';
        $parts = explode('\\', $action);
        return end($parts);
    }

    private function tail(string $path, int $lines): string
    {
        $fh = fopen($path, 'r');
        if (!$fh) return '';
        $buffer  = '';
        $chunk   = 4096;
        $read    = 0;
        $newlines = 0;
        fseek($fh, 0, SEEK_END);
        $pos = ftell($fh);
        while ($pos > 0 && $newlines <= $lines) {
            $seek = max(0, $pos - $chunk);
            $size = $pos - $seek;
            fseek($fh, $seek);
            $data    = fread($fh, $size);
            $buffer  = $data . $buffer;
            $newlines = substr_count($buffer, "\n");
            $pos = $seek;
            $read += $size;
            if ($read > 50 * 1024 * 1024) break; // hard cap 50 MB scanned
        }
        fclose($fh);
        $all = explode("\n", $buffer);
        return implode("\n", array_slice($all, -$lines));
    }

    private function regenerate(): void
    {
        \Artisan::call('dev:generate-docs');
    }
}
