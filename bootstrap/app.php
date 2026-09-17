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
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, \Illuminate\Http\Request $request) {
            if ($request->is('posts')) {
                $message = 'รูปหลักฐานต้องมีขนาดไม่เกิน 5 MB';
                return $request->expectsJson()
                    ? response()->json(['message'=>$message,'errors'=>['image'=>[$message]]], 413)
                    : redirect()->route('feed')->withErrors(['image'=>$message]);
            }
        });
    })->create();
