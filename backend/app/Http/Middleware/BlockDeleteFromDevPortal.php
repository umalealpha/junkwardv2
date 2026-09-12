<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Belt-and-braces backend enforcement for the dev-portal DELETE block.
 *
 * The Swagger UI itself disables DELETE execution (resources/views/dev/swagger.blade.php)
 * but a savvy user could still call a DELETE endpoint from curl using a
 * token minted by /dev/swagger-token. This middleware rejects ANY DELETE
 * request bearing a `dev-portal` token name — forcing destructive calls
 * through the real admin UI where they carry confirmation dialogs,
 * activity logs, and a real authenticated session.
 *
 * Registered on the api/v1 route group so every DELETE endpoint picks it
 * up automatically.
 */
class BlockDeleteFromDevPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('DELETE')) {
            $user = $request->user();
            // $user->currentAccessToken() returns the active Sanctum PAT row
            // if the request was authenticated via bearer token. Its ->name
            // is 'dev-portal' when minted by /dev/swagger-token.
            if ($user && method_exists($user, 'currentAccessToken')) {
                try {
                    $tok = $user->currentAccessToken();
                    if ($tok && isset($tok->name) && $tok->name === 'dev-portal') {
                        return new JsonResponse([
                            'error'   => 'DELETE operations are blocked when authenticated via the dev portal.',
                            'hint'    => 'Destructive calls must go through the admin UI where they carry confirmation + audit logging.',
                            'blocked_by' => 'BlockDeleteFromDevPortal',
                        ], 403);
                    }
                } catch (\Throwable $e) {
                    // Non-fatal — if the token introspection fails fall
                    // through to the normal request handling. Better to
                    // allow a legitimate DELETE than to block one because
                    // of a Sanctum edge case.
                }
            }
        }
        return $next($request);
    }
}
