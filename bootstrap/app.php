<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);

        $middleware->web(LocaleMiddleware::class);
        $middleware->api(ForceJsonResponse::class);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'active' => EnsureUserIsActive::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated. Please login first.',
                ], 401);
            }

            return redirect()->guest(route('login'));
        });

        // Keep API errors inside the same envelope the success responses use,
        // and never let a stack trace reach a client.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'status' => false,
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }

            // This callback runs after Laravel has already converted the
            // framework exceptions into their HTTP equivalents, so both
            // forms are matched here.
            $map = [
                AuthorizationException::class => [403, 'You are not allowed to do that.'],
                AccessDeniedHttpException::class => [403, 'You are not allowed to do that.'],
                ModelNotFoundException::class => [404, 'Resource not found.'],
                NotFoundHttpException::class => [404, 'Resource not found.'],
                MethodNotAllowedHttpException::class => [405, 'Method not allowed.'],
                ThrottleRequestsException::class => [429, 'Too many requests. Please slow down.'],
                TooManyRequestsHttpException::class => [429, 'Too many requests. Please slow down.'],
            ];

            foreach ($map as $class => [$status, $message]) {
                if ($e instanceof $class) {
                    return response()->json(['status' => false, 'message' => $message], $status);
                }
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage() ?: 'Request failed.',
                ], $e->getStatusCode());
            }

            return response()->json([
                'status' => false,
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : 'Something went wrong. Please try again.',
            ], 500);
        });
    })->create();
