<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Selama instalasi web belum selesai, semua halaman diarahkan ke /install. */
class RedirectToInstaller
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('aksi.installer') && ! $request->is('install', 'install/*', 'up')) {
            return redirect('/install');
        }

        return $next($request);
    }
}
