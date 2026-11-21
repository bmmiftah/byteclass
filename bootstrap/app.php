<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'aktif' => \App\Http\Middleware\AkunAktif::class,
        'admin' => \App\Http\Middleware\HanyaAdmin::class,
        'milik' => \App\Http\Middleware\PastikanMilik::class,
    ]);

    // 'milik' harus berjalan SETELAH alamat diubah menjadi model (SubstituteBindings)
    $middleware->appendToPriorityList(
        after: \Illuminate\Routing\Middleware\SubstituteBindings::class,
        append: \App\Http\Middleware\PastikanMilik::class,
    );
})
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
