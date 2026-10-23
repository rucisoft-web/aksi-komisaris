<?php

namespace App\Services;

use App\Enums\EvidenceStatus;
use App\Enums\IssueStatus;
use App\Enums\RequestStatus;
use App\Models\AuditLog;
use App\Models\InformationRequest;
use App\Models\Issue;
use App\Models\PersonalNote;
use App\Models\Space;
use App\Models\User;
use App\Support\AccessGate;
use Illuminate\Validation\ValidationException;

/**
 * Pemindahan catatan pribadi ke ruang resmi: disengaja, oleh pemilik,
 * dan menghasilkan salinan baru berstatus draf. Catatan asli tidak berubah.
 */
class NoteService
{
    public function __construct(protected AccessGate $gate) {}

    public function promote(PersonalNote $note, User $user, string $as, int $spaceId): Issue|InformationRequest
    {
        if ($note->user_id !== $user->id) {
            throw ValidationException::withMessages(['note' => 'Hanya pemilik catatan yang dapat memindahkannya ke ruang resmi.']);
        }

        $space = Space::query()->findOrFail($spaceId);

        if (! array_key_exists($space->id, $this->gate->writableOfficialSpaces($user, $space->company))) {
            throw ValidationException::withMessages(['space_id' => 'Ruang tujuan tidak tersedia untuk Anda.']);
        }

        $record = $as === 'pertanyaan'
            ? InformationRequest::query()->create([
                'space_id' => $space->id,
                'question' => $note->body,
                'basis' => 'Dari catatan pribadi: '.$note->title,
                'meeting_id' => $note->meeting_id,
                'status' => RequestStatus::Draf,
                'created_by' => $user->id,
            ])
            : Issue::query()->create([
                'space_id' => $space->id,
                'title' => $note->title,
                'description' => $note->body,
                'evidence_status' => EvidenceStatus::Indikasi,
                'status' => IssueStatus::Dibuka,
                'owner_user_id' => $user->id,
                'created_by' => $user->id,
            ]);

        $note->update(['promoted_to_type' => $record::class, 'promoted_to_id' => $record->id]);
        AuditLog::record('promoted', $note, ['to' => class_basename($record)]);

        return $record;
    }
}
