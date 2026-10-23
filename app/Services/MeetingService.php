<?php

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Models\AgendaMaterial;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Meeting;
use App\Models\Minute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeetingService
{
    /**
     * Bekukan paket rapat: setiap bahan dikunci ke versi saat ini dan
     * manifes hash disimpan, sehingga perubahan dokumen setelahnya tidak
     * mengubah apa yang dibahas.
     */
    public function freeze(Meeting $meeting, User $user): Meeting
    {
        if (! $user->canIn('rapat.kelola', $meeting->company_id)) {
            throw ValidationException::withMessages(['meeting' => 'Hanya sekretariat yang dapat membekukan paket rapat.']);
        }

        if ($meeting->isFrozen()) {
            throw ValidationException::withMessages(['meeting' => 'Paket rapat sudah dibekukan.']);
        }

        return DB::transaction(function () use ($meeting, $user) {
            $materials = AgendaMaterial::query()
                ->whereIn('agenda_item_id', $meeting->agendaItems()->pluck('id'))
                ->get();

            foreach ($materials as $material) {
                $versionId = Document::withoutGlobalScopes()->whereKey($material->document_id)->value('current_version_id');
                $material->update(['document_version_id' => $versionId]);
            }

            $meeting->update([
                'frozen_at' => now(),
                'frozen_by' => $user->id,
                'status' => MeetingStatus::PaketDibekukan,
                'manifest_sha256' => $this->manifestHash($meeting),
            ]);

            AuditLog::record('pack_frozen', $meeting, ['materials' => $materials->count()]);

            return $meeting;
        });
    }

    public function manifestHash(Meeting $meeting): string
    {
        $lines = AgendaMaterial::query()
            ->whereIn('agenda_item_id', $meeting->agendaItems()->pluck('id'))
            ->orderBy('agenda_item_id')->orderBy('document_id')
            ->get()
            ->map(fn (AgendaMaterial $m) => $m->agenda_item_id.':'.$m->document_id.':'.(DocumentVersion::withoutGlobalScopes()->whereKey($m->document_version_id)->value('sha256') ?? 'kosong'));

        return hash('sha256', $lines->implode("\n"));
    }

    /** Bahan yang ditambahkan setelah pembekuan dicatat sebagai adendum dengan versinya sendiri. */
    public function attachMaterial(Meeting $meeting, int $agendaItemId, int $documentId): AgendaMaterial
    {
        $isFrozen = $meeting->isFrozen();

        return AgendaMaterial::query()->firstOrCreate(
            ['agenda_item_id' => $agendaItemId, 'document_id' => $documentId],
            [
                'company_id' => $meeting->company_id,
                'is_addendum' => $isFrozen,
                'document_version_id' => $isFrozen ? Document::withoutGlobalScopes()->whereKey($documentId)->value('current_version_id') : null,
            ],
        );
    }

    public function approveMinutes(Minute $minute, User $user): void
    {
        if (! $user->canIn('risalah.sahkan', $minute->company_id)) {
            throw ValidationException::withMessages(['minute' => 'Anda tidak berwenang mengesahkan risalah.']);
        }

        DB::transaction(function () use ($minute, $user) {
            $minute->update(['status' => 'disahkan', 'approved_by' => $user->id, 'approved_at' => now()]);
            $minute->meeting->update(['status' => MeetingStatus::RisalahDisahkan]);
            AuditLog::record('minutes_approved', $minute);
        });
    }
}
