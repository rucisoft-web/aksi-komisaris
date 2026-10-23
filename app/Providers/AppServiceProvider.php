<?php

namespace App\Providers;

use App\Support\AccessGate;
use App\Support\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // scoped: direset setiap permintaan HTTP dan setiap job antrean.
        $this->app->scoped(CurrentCompany::class);
        $this->app->scoped(AccessGate::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('id');
    }
}
