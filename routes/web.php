<?php

use App\Http\Controllers\FileController;
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
