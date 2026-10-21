<?php

use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware()
    ->withExceptions(function (Exceptions $exceptions): void {
        // RF-AUD-001 CA-02: toda denegación de autorización queda registrada (las de archivos se registran con más detalle en su controlador).
        $exceptions->render(function (Throwable $e, Request $request) {
            $denied = $e instanceof AuthorizationException || ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 403);
            if ($denied && $request->user() && ! $request->routeIs('documents.file', 'documents.viewer')) {
                $resource = collect($request->route()?->parameters() ?? [])->first(fn ($value) => $value instanceof Model);
                Audit::denied('Acceso denegado a '.$request->method().' /'.ltrim($request->path(), '/'), $resource, ['resource_type' => 'route', 'route' => $request->route()?->getName()]);
            }

            return null;
        });
    })->create();
