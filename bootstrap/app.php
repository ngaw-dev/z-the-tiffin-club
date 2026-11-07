<?php

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Uniform JSON error envelope for every API request. Every failure is
        // rendered as { message, errors, status } so clients (and the future
        // frontend) can rely on one shape. See App\Support\ApiResponse.
        $renderJson = function (Throwable $e, int $status, ?string $message = null) {
            $message ??= $e->getMessage();

            if ($message === '' || $message === '0') {
                // E.g. NotFoundHttpException converted from ModelNotFoundException.
                $message = Response::$statusTexts[$status] ?? 'Server Error';
            }

            return ApiResponse::error(
                $message,
                $status,
                $e instanceof ValidationException ? $e->errors() : null,
            );
        };

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) use ($renderJson) {
            return $renderJson($e, 422, $e->getMessage());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($renderJson) {
            return $renderJson($e, 401, 'Unauthenticated.');
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($renderJson) {
            return $renderJson($e, 403);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($renderJson) {
            return $renderJson($e, 404, 'Resource not found.');
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($renderJson) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // keep Laravel's default (HTML) rendering for non-API routes
            }

            if ($e instanceof HttpExceptionInterface) {
                return $renderJson($e, $e->getStatusCode());
            }

            return $renderJson(
                $e,
                500,
                config('app.debug') ? null : 'Server Error. Please try again later.',
            );
        });
    })->create();
