<?php

use App\Enums\FollowUpStatus;
use App\Models\Company;
use App\Models\FollowUp;
use App\Models\Membership;
use App\Models\RequiredDocument;
use App\Models\RequiredDocumentReceipt;
use App\Models\User;
use App\Notifications\AksiNotification;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

/*
| Perintah operasional AKSI Komisaris.
| Di cPanel cukup satu cron per menit: php artisan schedule:run
*/

Artisan::command('aksi:create-admin {email} {name} {--password=}', function (string $email, string $name) {
    $password = $this->option('password') ?: $this->secret('Kata sandi (min. 10 karakter)');

    if (strlen((string) $password) < 10) {
        $this->error('Kata sandi minimal 10 karakter.');

        return 1;
    }

    $user = User::updateOrCreate(['email' => $email], [
        'name' => $name,
        'password' => $password,
        'is_platform_admin' => true,
        'is_active' => true,
    ]);

    $this->info("Admin platform siap: {$user->email}. Masuk lewat /platform untuk membuat perusahaan dan pengguna.");
})->purpose('Buat atau perbarui admin platform');

Artisan::command('aksi:required-documents', function () {
    $today = CarbonImmutable::today();
    $created = 0;

    foreach (Company::where('is_active', true)->get() as $company) {
        app(CurrentCompany::class)->runAs($company, function () use ($today, &$created) {
            foreach (RequiredDocument::where('is_active', true)->get() as $required) {
                $months = match ($required->frequency) {
                    'bulanan' => 1, 'triwulanan' => 3, 'semesteran' => 6, default => 12,
                };

                // Akhir periode terakhir yang sudah lewat, lalu mundur sampai 12 bulan.
                $end = $today->startOfMonth()->subDay();

                while ($end->month % $months !== 0) {
                    $end = $end->startOfMonth()->subDay();
                }

                for ($i = 0; $i < intdiv(12, $months); $i++) {
                    $label = match ($months) {
                        1 => $end->format('Y-m'),
                        3 => $end->year.'-Q'.(intdiv($end->month - 1, 3) + 1),
                        6 => $end->year.'-S'.($end->month <= 6 ? 1 : 2),
                        default => (string) $end->year,
                    };

                    $receipt = RequiredDocumentReceipt::firstOrCreate(
                        ['required_document_id' => $required->id, 'period_label' => $label],
                        ['period_end' => $end, 'due_date' => $end->addDays($required->due_days_after_period)],
                    );
                    $created += (int) $receipt->wasRecentlyCreated;

                    $end = $end->startOfMonth()->subMonthsNoOverflow($months - 1)->subDay();
                }
            }

            RequiredDocumentReceipt::whereNull('document_id')
                ->where('status', 'diharapkan')
                ->whereDate('due_date', '<', $today)
                ->update(['status' => 'belum_diterima']);
        });
    }

    $this->info("Periode dokumen wajib baru: {$created}.");
})->purpose('Buat daftar penerimaan dokumen wajib per periode dan tandai yang belum diterima');

Artisan::command('aksi:followups', function () {
    $today = CarbonImmutable::today();
    $remind = config('aksi.followups.remind_days_before');
    $escalateAfter = (int) config('aksi.followups.escalate_days_after');
    $sent = 0;

    foreach (Company::where('is_active', true)->get() as $company) {
        app(CurrentCompany::class)->runAs($company, function () use ($company, $today, $remind, $escalateAfter, &$sent) {
            $open = FollowUp::whereIn('status', [
                FollowUpStatus::Disampaikan, FollowUpStatus::Ditanggapi, FollowUpStatus::Dilaksanakan, FollowUpStatus::DibukaKembali,
            ])->with('pic', 'reviewer')->get();

            foreach ($open as $followUp) {
                $days = (int) $today->diffInDays($followUp->target_date, false);
                $url = url("/app/{$company->slug}/follow-ups/{$followUp->id}");

                if (in_array($days, $remind, true) && $followUp->pic) {
                    $followUp->pic->notify(new AksiNotification('Tenggat tindak lanjut '.$days.' hari lagi', (string) str($followUp->action_text)->limit(120), $url));
                    $sent++;
                }

                // Eskalasi bertingkat: 1 = lewat tenggat (PIC dan reviewer), 2 = lewat N hari (Komisaris Utama).
                $level = $days < -$escalateAfter ? 2 : ($days < 0 ? 1 : 0);

                if ($level > $followUp->escalation_level) {
                    $recipients = $level === 1
                        ? collect([$followUp->reviewer, $followUp->pic])
                        : Membership::where('role', 'komisaris_utama')->where('is_active', true)->with('user')->get()->pluck('user');

                    foreach ($recipients->filter() as $recipient) {
                        $recipient->notify(new AksiNotification(
                            $level === 1 ? 'Tindak lanjut melewati tenggat' : 'Eskalasi: tindak lanjut terlambat lebih dari '.$escalateAfter.' hari',
                            (string) str($followUp->action_text)->limit(120),
                            $url,
                        ));
                        $sent++;
                    }

                    $followUp->forceFill(['escalation_level' => $level])->saveQuietly();
                }
            }
        });
    }

    $this->info("Notifikasi terkirim: {$sent}.");
})->purpose('Pengingat tenggat dan eskalasi tindak lanjut');

Artisan::command('aksi:deactivate-expired', function () {
    $count = Membership::withoutGlobalScopes()
        ->where('is_active', true)
        ->whereNotNull('valid_until')
        ->whereDate('valid_until', '<', today())
        ->update(['is_active' => false]);

    DB::table('space_members')->whereNotNull('valid_until')->whereDate('valid_until', '<', today())->delete();

    $this->info("Keanggotaan dinonaktifkan: {$count}.");
})->purpose('Nonaktifkan keanggotaan yang masa jabatannya berakhir');

Artisan::command('aksi:demo', function () {
    $this->call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);
})->purpose('Isi data demo BUMN (PT Energi Nusantara (Persero))');

Artisan::command('aksi:doctor', function () {
    $disk = Storage::disk(config('aksi.uploads.disk'));

    $checks = [
        'PHP >= 8.3' => version_compare(PHP_VERSION, '8.3.0', '>='),
        'Ekstensi pdo_mysql' => extension_loaded('pdo_mysql'),
        'Ekstensi mbstring' => extension_loaded('mbstring'),
        'Ekstensi zip (DOCX/XLSX/PPTX)' => extension_loaded('zip'),
        'Ekstensi intl' => extension_loaded('intl'),
        'Ekstensi fileinfo' => extension_loaded('fileinfo'),
        'APP_KEY terisi' => filled(config('app.key')),
        'Koneksi database' => rescue(fn () => (bool) DB::select('select 1'), false, false),
        'Penyimpanan berkas bisa ditulis' => rescue(fn () => $disk->put('.doctor', 'ok') && $disk->delete('.doctor'), false, false),
        'Antrean memakai database' => config('queue.default') === 'database' || config('aksi.ai.run_sync'),
    ];

    $optional = [
        'pdftotext (PDF lebih akurat)' => is_executable((string) config('aksi.extraction.pdftotext')),
        'tesseract (OCR hasil pindai)' => trim((string) @shell_exec('command -v '.escapeshellarg((string) config('aksi.extraction.tesseract')).' 2>/dev/null')) !== '',
        'OPENAI_API_KEY (fitur AI)' => filled(config('aksi.ai.api_key')),
        'Antrean diproses cron (tidak ada job > 10 menit)' => rescue(fn () => DB::table('jobs')->where('created_at', '<', now()->subMinutes(10)->timestamp)->count() === 0, false, false),
    ];

    $this->table(['Pemeriksaan wajib', 'Hasil'], collect($checks)->map(fn ($ok, $name) => [$name, $ok ? 'OK' : 'GAGAL'])->values()->all());
    $this->table(['Pemeriksaan opsional', 'Hasil'], collect($optional)->map(fn ($ok, $name) => [$name, $ok ? 'OK' : 'tidak ada'])->values()->all());

    return in_array(false, $checks, true) ? 1 : 0;
})->purpose('Periksa kesiapan server');

// Di cPanel: * * * * * cd /home/USER/aksi && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5);
Schedule::command('aksi:deactivate-expired')->dailyAt('00:10');
Schedule::command('aksi:required-documents')->dailyAt('06:30');
Schedule::command('aksi:followups')->dailyAt('07:00');
Schedule::command('queue:prune-failed --hours=720')->weekly();
