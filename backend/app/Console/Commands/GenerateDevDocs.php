<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * Regenerate the static dev docs under `backend/dev/`:
 *   - API_ENDPOINTS.md   human-readable list grouped by top-level segment
 *   - openapi.json       OpenAPI 3.0 paths (methods + tags + bearer security)
 *
 * Run after any route change:
 *   php artisan dev:generate-docs
 */
class GenerateDevDocs extends Command
{
    protected $signature   = 'dev:generate-docs {--print : echo to stdout instead of writing files}';
    protected $description = 'Regenerate backend/dev/API_ENDPOINTS.md + openapi.json from the current route list.';

    public function handle(): int
    {
        // Filter to only JSON API routes — the docs are for consumers of
        // /api/v1/*, not the admin / web / dev routes (which mix Blade,
        // Livewire, and internal-only endpoints). `$r->uri()` already
        // includes the 'api/v1' prefix since RouteServiceProvider applies
        // it via ->prefix('api') + the group().
        $routes = collect(Route::getRoutes())->map(function ($r) {
            $methods = array_values(array_filter($r->methods(), fn ($m) => $m !== 'HEAD'));
            return [
                'methods'    => $methods,
                'uri'        => '/' . ltrim($r->uri(), '/'),
                'name'       => $r->getName(),
                'action'     => $r->getActionName(),
                'middleware' => array_values($r->gatherMiddleware()),
            ];
        })
        ->filter(fn ($r) => str_starts_with($r['uri'], '/api/v1/') || $r['uri'] === '/api/v1')
        // Strip routes that aren't actually in use by the FE / any external
        // consumer. These are historical imports, debug stubs, unused
        // controller scaffolds — they bloat Swagger and confuse users.
        // Match against URI substrings rather than exact paths so each
        // entry covers a whole family.
        ->reject(function ($r) {
            $uri = $r['uri'];
            $skip = [
                '/api/v1/debug/',
                '/api/v1/test/',
                '/api/v1/_internal/',
                '/api/v1/dev/',
                '/api/v1/sample/',
                '/api/v1/legacy/',
            ];
            foreach ($skip as $s) {
                if (str_starts_with($uri, $s)) return true;
            }
            // Strip Closure routes that have no controller — usually
            // one-off debug stubs or ping endpoints.
            if ($r['action'] === 'Closure' && !str_contains($uri, 'health')) {
                return true;
            }
            return false;
        })
        ->sortBy('uri')->values();

        $md    = $this->renderMarkdown($routes);
        $oas   = $this->renderOpenApi($routes);

        if ($this->option('print')) {
            $this->line($md);
            $this->newLine();
            $this->line(json_encode($oas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $devDir = base_path('dev');
        if (!File::isDirectory($devDir)) File::makeDirectory($devDir, 0755, true);

        File::put($devDir . '/API_ENDPOINTS.md', $md);
        File::put($devDir . '/openapi.json', json_encode($oas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('Wrote ' . $devDir . '/API_ENDPOINTS.md (' . $routes->count() . ' routes)');
        $this->info('Wrote ' . $devDir . '/openapi.json');
        return self::SUCCESS;
    }

    private function renderMarkdown(\Illuminate\Support\Collection $routes): string
    {
        $now = now()->toIso8601String();
        $grouped = $routes->groupBy(function ($r) {
            $parts = explode('/', trim($r['uri'], '/'));
            return $parts[0] ?? '(root)';
        })->sortKeys();

        $lines = [];
        $lines[] = '# API Endpoints';
        $lines[] = '';
        $lines[] = "_Generated from `Route::getRoutes()` at {$now}. Regenerate via `php artisan dev:generate-docs`._";
        $lines[] = '';
        $lines[] = '- **Base URL:** `https://graphite-v2-be.alphadirect.co.bw` (prod)';
        $lines[] = '- **Prefix:** every path below is served under `/api/v1/...`. The table URIs already include the prefix.';
        $lines[] = '- **Auth:** `Authorization: Bearer <sanctum-token>` except where noted (public endpoints / webhooks).';
        $lines[] = '- Total routes: **' . $routes->count() . '**';
        $lines[] = '';
        $lines[] = '## Groups';
        $lines[] = '';
        foreach ($grouped as $g => $rs) {
            $lines[] = '- [`' . $g . '`](#' . strtolower(str_replace(['/', ' '], '-', $g)) . ') — ' . $rs->count() . ' routes';
        }
        $lines[] = '';

        foreach ($grouped as $g => $rs) {
            $lines[] = '## ' . $g;
            $lines[] = '';
            $lines[] = '| Method | URI | Controller | Middleware |';
            $lines[] = '| --- | --- | --- | --- |';
            foreach ($rs as $r) {
                $methods = implode(' \| ', $r['methods']);
                $uri     = '`' . $r['uri'] . '`';
                $action  = $this->shortAction($r['action']);
                $middle  = implode(', ', array_map(fn ($m) => '`' . $m . '`', $r['middleware']));
                $lines[] = "| {$methods} | {$uri} | {$action} | {$middle} |";
            }
            $lines[] = '';
        }
        return implode("\n", $lines) . "\n";
    }

    private function renderOpenApi(\Illuminate\Support\Collection $routes): array
    {
        $paths = [];
        foreach ($routes as $r) {
            $uri  = $r['uri'];
            // Laravel {id} / {id?} -> OpenAPI {id}
            $oasUri = preg_replace('/\{(\w+)\??\}/', '{$1}', $uri);
            $pathParams = [];
            if (preg_match_all('/\{(\w+)\??\}/', $uri, $m)) {
                foreach ($m[1] as $name) {
                    $pathParams[] = [
                        'name'     => $name,
                        'in'       => 'path',
                        'required' => true,
                        'schema'   => ['type' => 'string'],
                    ];
                }
            }
            $tag = explode('/', trim($uri, '/'))[0] ?? 'root';
            $needsAuth = !in_array($tag, ['webhooks', 'health'], true)
                && !($tag === 'auth' && in_array(strtolower($r['action']), [
                    'alphadirect\\http\\controllers\\api\\v1\\authcontroller@login',
                    'alphadirect\\http\\controllers\\api\\v1\\authcontroller@setuppassword',
                    'alphadirect\\http\\controllers\\api\\v1\\authcontroller@exchangessotoken',
                ], true));

            // Introspect the controller method for $request->validate([...])
            // rules — gives us real query/body params in Swagger UI instead
            // of an empty "no parameters" section.
            $validated = $this->extractValidatedRules($r['action']);

            // Build a JSON Schema fragment for a single rule. OpenAPI 3.0
            // requires `items` whenever `type: array` — Swagger UI's strict
            // validator (the one shown in /dev/swagger errors) refuses the
            // spec without it. Default to string items so the form still
            // renders cleanly when the underlying Laravel rules don't tell
            // us the inner type.
            $schemaFor = function (array $v): array {
                $s = ['type' => $v['type']];
                if ($v['type'] === 'array') {
                    $s['items'] = ['type' => 'string'];
                }
                if (!empty($v['description'])) $s['description'] = $v['description'];
                return $s;
            };

            foreach ($r['methods'] as $method) {
                $params = $pathParams;
                $requestBody = null;

                // GET/DELETE/HEAD put validated fields in the query string.
                // POST/PUT/PATCH put them in the JSON body (Swagger
                // auto-generates a "Try it out" form from the schema).
                $isBodyMethod = in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true);
                if (!empty($validated)) {
                    if ($isBodyMethod) {
                        $requestBody = [
                            'required' => collect($validated)->contains(fn ($v) => $v['required']),
                            'content'  => [
                                'application/json' => [
                                    'schema' => [
                                        'type'       => 'object',
                                        'required'   => collect($validated)->filter(fn ($v) => $v['required'])->keys()->values()->all(),
                                        'properties' => collect($validated)->mapWithKeys(fn ($v, $k) => [
                                            $k => $schemaFor($v),
                                        ])->all(),
                                    ],
                                ],
                            ],
                        ];
                    } else {
                        foreach ($validated as $name => $v) {
                            $params[] = [
                                'name'        => $name,
                                'in'          => 'query',
                                'required'    => $v['required'],
                                'schema'      => $schemaFor($v),
                                'description' => $v['description'],
                            ];
                        }
                    }
                }

                // operationId must be unique across the whole spec. Falling
                // back to $r['name'] alone collided whenever a route's name
                // shadowed another (Laravel's resource controllers reuse
                // the same `name` for index+store and for show+update+destroy
                // by varying only the suffix dynamically). Always prefix with
                // the HTTP method + add a path hash if necessary.
                $methodLower = strtolower($method);
                $baseId = $r['name']
                    ? "{$methodLower}_{$r['name']}"
                    : ($methodLower . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $uri));
                static $usedOpIds = [];
                $opId = $baseId;
                $suffix = 2;
                while (isset($usedOpIds[$opId])) {
                    $opId = $baseId . '_' . $suffix;
                    $suffix++;
                }
                $usedOpIds[$opId] = true;

                $op = [
                    'tags'        => [$tag],
                    'summary'     => $r['name'] ?: ($method . ' ' . $uri),
                    'operationId' => $opId,
                    'parameters'  => $params,
                    'responses'   => [
                        // Declaring application/json on 200 makes Swagger UI
                        // set `Accept: application/json` on "Try it out"
                        // fetches — without it the request gets Accept: */*,
                        // which flips Laravel's expectsJson() to false and
                        // redirects unauthenticated requests to an HTML
                        // login/dashboard page instead of returning 401 JSON.
                        '200' => [
                            'description' => 'OK',
                            'content'     => ['application/json' => ['schema' => ['type' => 'object']]],
                        ],
                        '401' => ['description' => 'Unauthenticated'],
                        '422' => ['description' => 'Validation failed'],
                    ],
                ];
                if ($requestBody) $op['requestBody'] = $requestBody;
                if ($needsAuth)   $op['security']    = [['sanctumToken' => []]];
                $paths[$oasUri] ??= [];
                $paths[$oasUri][strtolower($method)] = $op;
            }
        }

        return [
            'openapi' => '3.0.3',
            'info'    => [
                'title'       => 'Alpha Direct V2 API',
                'version'     => '1.0.0',
                'description' => 'Auto-generated from Route::getRoutes(). Regenerate via `php artisan dev:generate-docs`.',
            ],
            'servers' => [
                // Paths in this spec already include the /api/v1 prefix
                // (routes are registered under Route::prefix('api')->
                // group(...api_v1.php), so $r->uri() returns 'api/v1/...').
                // Keeping only prod here — local/staging can be added back
                // via the Swagger UI "Servers" dropdown if needed.
                ['url' => 'https://graphite-v2-be.alphadirect.co.bw', 'description' => 'Production'],
            ],
            'components' => [
                'securitySchemes' => [
                    'sanctumToken' => [
                        'type'         => 'http',
                        'scheme'       => 'bearer',
                        'bearerFormat' => 'Sanctum PAT (format: {id}|{secret})',
                    ],
                ],
            ],
            'security' => [['sanctumToken' => []]],
            'paths'    => $paths,
        ];
    }

    private function shortAction(string $action): string
    {
        if ($action === 'Closure') return '`Closure`';
        $parts = explode('\\', $action);
        return '`' . end($parts) . '`';
    }

    /**
     * Introspect the controller method body and pull out the first
     * `$request->validate([ ... ])` call. Returns an associative array
     * [fieldName => ['type' => 'string', 'required' => bool, 'description' => '...']].
     * Returns [] if the action is a Closure, if reflection fails, or if
     * no validate() call is present.
     *
     * This is a best-effort regex-based parser. It catches the common
     * controller pattern used across the codebase
     * (`$validated = $request->validate([...]);`) and handles rule strings
     * plus nested arrays. Exotic patterns (inline FormRequest injection,
     * dynamic rule arrays) just fall through to "no parameters" — same
     * as before the enhancement.
     *
     * @return array<string,array{type:string,required:bool,description:string}>
     */
    private function extractValidatedRules(string $action): array
    {
        if ($action === 'Closure' || !str_contains($action, '@')) return [];
        [$class, $method] = explode('@', $action);
        try {
            $refClass = new \ReflectionClass($class);
            if (!$refClass->hasMethod($method)) return [];
            $refMethod = $refClass->getMethod($method);
            $file  = $refMethod->getFileName();
            $start = $refMethod->getStartLine();
            $end   = $refMethod->getEndLine();
            if (!$file || !is_file($file)) return [];
            $lines = array_slice(file($file), $start - 1, $end - $start + 1);
            $body  = implode('', $lines);
        } catch (\Throwable $e) {
            return [];
        }

        // Match a validate-rules array. Covers every common pattern:
        //   $request->validate([ ... ])
        //   $this->validate($request, [ ... ])
        //   Validator::make($request->all(), [ ... ])
        //   $data = $request->validate([ ... ])
        // Picks the first match in the method body.
        $patterns = [
            'request->validate([',
            '$this->validate($request, [',
            '$this->validate($request,[',
            'Validator::make($request->all(), [',
            'Validator::make($request->all(),[',
            'Validator::make($data, [',
            'Validator::make($data,[',
        ];
        $start = null;
        foreach ($patterns as $needle) {
            $pos = strpos($body, $needle);
            if ($pos !== false) {
                $start = $pos + strlen($needle);
                break;
            }
        }
        if ($start === null) return [];
        $depth  = 1;
        $len    = strlen($body);
        $end    = null;
        for ($i = $start; $i < $len; $i++) {
            $ch = $body[$i];
            if ($ch === '[') $depth++;
            elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) { $end = $i; break; }
            }
        }
        if ($end === null) return [];
        $inner = substr($body, $start, $end - $start);

        // Pull out "'field' => 'rule|rule|rule'" and "'field' => ['rule', Rule::...]"
        // We treat array-form rules as strings joined by | for type inference.
        $fields = [];
        if (preg_match_all("/'([A-Za-z0-9_.\\-\\*]+)'\s*=>\s*(?:'([^']*)'|\[([^\]]*)\])/s", $inner, $m, PREG_SET_ORDER)) {
            foreach ($m as $entry) {
                $name  = $entry[1];
                $rules = !empty($entry[2])
                    ? $entry[2]
                    : preg_replace_callback("/'([^']+)'/", fn ($x) => $x[1], $entry[3]);
                // Skip nested / wildcard fields ("items.*.id") — OpenAPI
                // can't represent them cleanly as flat query params.
                if (str_contains($name, '.') || str_contains($name, '*')) continue;
                $fields[$name] = $this->inferParam($rules);
            }
        }
        return $fields;
    }

    /**
     * Map a Laravel rule string to a minimal OpenAPI parameter descriptor.
     * Intentionally coarse — good enough for Swagger UI to render a form.
     */
    private function inferParam(string $rules): array
    {
        $r = strtolower($rules);
        $required = str_contains($r, 'required') && !str_contains($r, 'nullable') && !str_contains($r, 'sometimes');
        $type = 'string';
        if (preg_match('/\b(integer|numeric|int)\b/', $r))       $type = 'integer';
        elseif (preg_match('/\b(boolean|bool)\b/', $r))          $type = 'boolean';
        elseif (preg_match('/\bdate(_format)?\b/', $r))          $type = 'string'; // keep string, format goes in desc
        elseif (preg_match('/\barray\b/', $r))                   $type = 'array';

        // Human-readable hint about the rule set — shown under the field
        // in Swagger UI so developers see "max:100, nullable" etc.
        $desc = trim(preg_replace('/\s+/', ' ', $rules));
        if (strlen($desc) > 120) $desc = substr($desc, 0, 117) . '...';

        return ['type' => $type, 'required' => $required, 'description' => $desc];
    }
}
