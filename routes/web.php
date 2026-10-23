<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\InstallController;
use App\Models\DocumentVersion;
use App\Models\FollowUpEvidence;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');

// Model dicari tanpa global scope perusahaan; otorisasi dilakukan di controller.
Route::bind('version', fn ($id) => DocumentVersion::withoutGlobalScopes()->findOrFail($id));
Route::bind('evidence', fn ($id) => FollowUpEvidence::withoutGlobalScopes()->findOrFail($id));

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/berkas/{version}', [FileController::class, 'version'])->name('berkas.lihat');
    Route::get('/bukti/{evidence}', [FileController::class, 'evidence'])->name('bukti.lihat');
});

Route::get('/login', fn () => redirect('/app/login'))->name('login');

// Instalasi lewat browser (paket siap-upload). Mati otomatis setelah selesai.
Route::middleware('web')->group(function () {
    Route::get('/install', [InstallController::class, 'show']);
    Route::post('/install', [InstallController::class, 'store'])->middleware('throttle:10,1');
});
