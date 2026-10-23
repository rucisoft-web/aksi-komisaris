<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan migrate --seed
     *
     * Admin platform dibuat dari AKSI_ADMIN_EMAIL/AKSI_ADMIN_PASSWORD bila diisi,
     * dan data demo BUMN dimuat bila AKSI_DEMO=true.
     */
    public function run(): void
    {
        if (filled(env('AKSI_ADMIN_EMAIL')) && filled(env('AKSI_ADMIN_PASSWORD'))) {
            User::updateOrCreate(['email' => env('AKSI_ADMIN_EMAIL')], [
                'name' => 'Admin Platform',
                'password' => env('AKSI_ADMIN_PASSWORD'),
                'is_platform_admin' => true,
                'is_active' => true,
            ]);
        }

        if (filter_var(env('AKSI_DEMO', false), FILTER_VALIDATE_BOOL)) {
            $this->call(DemoSeeder::class);
        }
    }
}
