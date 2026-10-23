<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentVersion;
use App\Models\FollowUpEvidence;
use App\Models\User;
use App\Support\AccessGate;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Berkas tidak pernah diletakkan di folder publik. Semua unduhan lewat sini:
 * diperiksa AccessGate, dicatat di audit log, dan diberi penanda identitas.
 */
class FileController extends Controller
{
    public function version(DocumentVersion $version, AccessGate $gate, CurrentCompany $current): StreamedResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $document = $version->document()->withoutGlobalScopes()->with('company')->firstOrFail();

        abort_unless($gate->canViewDocument($user, $document), 403, 'Anda tidak memiliki akses ke dokumen ini.');

        $current->set($document->company);
        AuditLog::record('download', $version, ['user_email' => $user->email]);

        return $this->stream($version->disk_path, $version->original_name, $user);
    }

    public function evidence(FollowUpEvidence $evidence, CurrentCompany $current): StreamedResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $followUp = $evidence->followUp()->withoutGlobalScopes()->firstOrFail();
        $role = $user->roleIn($followUp->company_id);

        abort_unless(
            $role && ($user->canIn('tindaklanjut.lihat', $followUp->company_id) || $followUp->pic_user_id === $user->id),
            403,
        );
        abort_if(! $evidence->disk_path, 404);

        $current->set($followUp->company);
        AuditLog::record('download', $evidence, ['user_email' => $user->email]);

        return $this->stream($evidence->disk_path, $evidence->original_name ?? 'bukti', $user);
    }

    protected function stream(string $path, string $name, User $user): StreamedResponse
    {
        $disk = Storage::disk(config('aksi.uploads.disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $name, [
            'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
            'X-Accessed-By' => $user->email,
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
