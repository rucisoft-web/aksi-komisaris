<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CurrentCompany;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyalin tenant Filament ke CurrentCompany dan memeriksa ulang keanggotaan
 * aktif pada setiap permintaan (masa jabatan bisa berakhir di tengah sesi).
 */
class SetCurrentCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Company) {
            abort_unless($request->user()?->activeMembership($tenant), 403, 'Keanggotaan Anda di perusahaan ini tidak aktif.');
            app(CurrentCompany::class)->set($tenant);
        }

        return $next($request);
    }
}
