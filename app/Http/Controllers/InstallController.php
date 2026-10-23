<?php

namespace App\Http\Controllers;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Instalasi lewat browser untuk paket siap-upload (cPanel tanpa Terminal).
 *
 * Hanya aktif bila AKSI_INSTALLER=true di .env. Setelah selesai, nilai itu
 * diubah menjadi false sehingga halaman ini tidak bisa dibuka lagi.
 */
class InstallController
{
    public function show(Request $request)
    {
        abort_unless(config('aksi.installer'), 404);

        return view('install', ['checks' => $this->checks(), 'defaults' => ['app_url' => $request->root()]]);
    }

    public function store(Request $request)
    {
        abort_unless(config('aksi.installer'), 404);
        @set_time_limit(300);

        $data = $request->validate([
            'app_url' => ['required', 'url'],
            'db_host' => ['required', 'string'],
            'db_port' => ['required', 'integer'],
            'db_database' => ['required', 'string'],
            'db_username' => ['required', 'string'],
            'db_password' => ['nullable', 'string'],
            'admin_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'email'],
            'admin_password' => ['required', 'string', 'min:10', 'confirmed'],
            'demo' => ['nullable', 'boolean'],
            'mfa' => ['nullable', 'boolean'],
            'no_cron' => ['nullable', 'boolean'],
            'openai_key' => ['nullable', 'string'],
        ]);

        if (in_array(false, $this->checks(), true)) {
            return back()->withInput()->withErrors(['install' => 'Persyaratan server belum terpenuhi (lihat daftar di atas).']);
        }

        try {
            new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s', $data['db_host'], $data['db_port'], $data['db_database']), $data['db_username'], $data['db_password'] ?? '');
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['db_database' => 'Tidak bisa terhubung ke database: '.$e->getMessage()]);
        }

        $noCron = (bool) ($data['no_cron'] ?? false);
        $this->writeEnv([
            'APP_KEY' => 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher'))),
            'APP_URL' => rtrim($data['app_url'], '/'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => (string) $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'] ?? '',
            'SESSION_SECURE_COOKIE' => str_starts_with($data['app_url'], 'https://') ? 'true' : 'false',
            'AKSI_MFA_REQUIRED' => ($data['mfa'] ?? false) ? 'true' : 'false',
            'AKSI_DEMO' => ($data['demo'] ?? false) ? 'true' : 'false',
            'QUEUE_CONNECTION' => $noCron ? 'sync' : 'database',
            'AKSI_AI_RUN_SYNC' => $noCron ? 'true' : 'false',
            'OPENAI_API_KEY' => $data['openai_key'] ?? '',
        ]);

        // Pakai koneksi baru untuk proses ini juga.
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $data['db_host'],
            'database.connections.mysql.port' => $data['db_port'],
            'database.connections.mysql.database' => $data['db_database'],
            'database.connections.mysql.username' => $data['db_username'],
            'database.connections.mysql.password' => $data['db_password'] ?? '',
        ]);
        DB::purge('mysql');

        try {
            Artisan::call('migrate', ['--force' => true]);

            User::updateOrCreate(['email' => $data['admin_email']], [
                'name' => $data['admin_name'],
                'password' => $data['admin_password'],
                'is_platform_admin' => true,
                'is_active' => true,
            ]);

            if ($data['demo'] ?? false) {
                (new DemoSeeder)->run();
            }
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['install' => 'Instalasi gagal: '.$e->getMessage()]);
        }

        $this->writeEnv(['AKSI_INSTALLER' => 'false']);
        Artisan::call('optimize:clear');

        return view('install', [
            'done' => true,
            'demo' => (bool) ($data['demo'] ?? false),
            'noCron' => $noCron,
            'cron' => 'cd '.base_path().' && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1',
            'appUrl' => rtrim($data['app_url'], '/'),
        ]);
    }

    /** @return array<string, bool> */
    protected function checks(): array
    {
        return [
            'PHP 8.3 atau lebih baru (sekarang '.PHP_VERSION.')' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'Ekstensi pdo_mysql' => extension_loaded('pdo_mysql'),
            'Ekstensi mbstring' => extension_loaded('mbstring'),
            'Ekstensi intl' => extension_loaded('intl'),
            'Ekstensi zip' => extension_loaded('zip'),
            'Ekstensi fileinfo' => extension_loaded('fileinfo'),
            'File .env bisa ditulis' => is_writable(base_path('.env')),
            'Folder storage bisa ditulis' => is_writable(storage_path()) && is_writable(storage_path('app')),
            'Folder bootstrap/cache bisa ditulis' => is_writable(base_path('bootstrap/cache')),
        ];
    }

    /** @param  array<string, string>  $values */
    protected function writeEnv(array $values): void
    {
        $path = base_path('.env');
        $content = file_get_contents($path);

        foreach ($values as $key => $value) {
            $line = $key.'='.(preg_match('/[\s#"\'$\\\\]/', $value) ? '"'.addcslashes($value, '"\\$').'"' : $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content) ? preg_replace_callback($pattern, fn () => $line, $content) : rtrim($content)."\n".$line."\n";
        }

        file_put_contents($path, $content);
    }
}
