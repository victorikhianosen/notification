<?php

use App\Traits\HttpResponses;
use Illuminate\Foundation\Application;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    // avoid strict type here to be compatible across Laravel versions
    ->withMiddleware(function ($middleware): void {
        //
    })
    ->withExceptions(function ($exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request = null) {

            // Temporary responder using your trait
            $responder = new class {
                use \App\Traits\HttpResponses;
            };

            $http = 500;
            $message = 'Internal server error.';
            $errors = null;

            if ($e instanceof ValidationException) {
                $http = 422;
                $errors = $e->errors();
                $message = is_array($errors) ? collect($errors)->flatten()->first() ?? 'Validation failed.' : 'Validation failed.';
            } elseif ($e instanceof AuthenticationException) {
                $http = 401;
                $message = $e->getMessage() ?: 'Unauthenticated.';
            } elseif ($e instanceof AuthorizationException) {
                $http = 403;
                $message = $e->getMessage() ?: 'Forbidden.';
            } elseif ($e instanceof ThrottleRequestsException || $e instanceof TooManyRequestsHttpException) {
                $http = 429;
                $message = 'Too many requests.';
            } elseif ($e instanceof ModelNotFoundException) {
                $http = 404;
                $model = class_basename($e->getModel() ?? 'Resource');
                $message = "{$model} not found.";
            } elseif ($e instanceof NotFoundHttpException) {
                $http = 404;
                $message = 'Route not found.';
            } elseif ($e instanceof MethodNotAllowedHttpException) {
                $http = 405;
                $message = 'Method not allowed.';
            } elseif ($e instanceof HttpExceptionInterface) {
                $http = $e->getStatusCode();
                $message = $e->getMessage() ?: 'HTTP error.';
            } else {
                $http = 500;
                $message = $e->getMessage() ?: 'Internal server error.';
            }

            // Build JSON using the trait (which returns a JsonResponse with default code)
            $jsonResponse = $responder->error(
                message: $message,
                // errors: $errors
            );

            if (env('APP_DEBUG', false)) {
                $debug = [
                    'exception' => get_class($e),
                    'trace' => collect($e->getTrace())->map(function ($item) {
                        return ($item['file'] ?? '') . ':' . ($item['line'] ?? '');
                    })->take(20)->values()->all()
                ];

                $data = $jsonResponse->getData(true); // JSON to array
                $data['debug'] = $debug;
                $jsonResponse->setData($data);
            }

            // Override HTTP status code here and return
            return $jsonResponse->setStatusCode($http);
        });
    })
    ->create();
