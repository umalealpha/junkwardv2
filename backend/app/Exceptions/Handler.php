<?php

namespace AlphaDirect\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * Attaches the raw Throwable to the request attributes so
     * TrackAPIRequest::terminate() can capture class, message, file,
     * line, and stack trace into api_error_log.error_data — no
     * controller try/catch needed.
     */
    public function render($request, Throwable $e)
    {
        if ($request instanceof Request) {
            $request->attributes->set('_exception', $e);
        }
        return parent::render($request, $e);
    }

    /**
     * Convert an authentication exception into a response.
     *
     * Laravel's default renders this as JSON 401 only if `$request->
     * expectsJson()` is true — which depends on the Accept header. When
     * Swagger UI (or any tool not setting Accept: application/json) hits
     * an /api/v1/* endpoint without a valid bearer token, the default
     * fell through to `redirect()->guest(route('login'))`. If the user
     * was already logged in via the admin web session, the login route
     * sent them to /admin/dashboard and we returned the entire dashboard
     * HTML page as the "API response" — which is what devs hitting
     * /dev/swagger were seeing.
     *
     * Treat any /api/* path (or any request carrying a Bearer token) as
     * JSON-expecting regardless of the Accept header. Browser routes
     * keep the old redirect behaviour.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        $isApi = $request->is('api/*')
            || str_starts_with((string) $request->header('Authorization', ''), 'Bearer ');

        if ($isApi || $request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }
        return redirect()->guest($exception->redirectTo($request) ?? route('login'));
    }
}
