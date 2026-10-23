<?php

namespace App\Services;

use App\Enums\FollowUpStatus as S;
use App\Models\AuditLog;
use App\Models\FollowUp;
use App\Models\FollowUpDeadlineChange;
use App\Models\FollowUpEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mesin status tindak lanjut:
 * Draf -> Disahkan -> Disampaikan -> Ditanggapi -> Dilaksanakan -> Diperiksa -> Ditutup / Dibuka kembali.
 *
 * Status pelaksanaan dan ketepatan waktu disimpan terpisah. Penutupan wajib
 * disertai bukti terverifikasi dan dilakukan oleh reviewer berwenang.
 */
class FollowUpService
{
    /** Transisi yang sah: dari => [ke => izin yang dibutuhkan]. */
    public const TRANSITIONS = [
        'draf' => ['disahkan' => 'tenggat.setujui'],
        'disahkan' => ['disampaikan' => 'tindaklanjut.kelola'],
        'disampaikan' => ['ditanggapi' => 'tindaklanjut.tanggapi'],
        'ditanggapi' => ['dilaksanakan' => 'tindaklanjut.tanggapi'],
        'dilaksanakan' => ['diperiksa' => 'tindaklanjut.tanggapi'],
        'diperiksa' => ['ditutup' => 'tindaklanjut.periksa', 'dilaksanakan' => 'tindaklanjut.periksa'],
        'ditutup' => ['dibuka_kembali' => 'tindaklanjut.periksa'],
        'dibuka_kembali' => ['dilaksanakan' => 'tindaklanjut.tanggapi'],
    ];

    /** @return array<string, string> status tujuan => label, yang boleh dilakukan pengguna ini. */
    public function availableTransitions(FollowUp $followUp, User $user): array
    {
        return collect(self::TRANSITIONS[$followUp->status->value] ?? [])
            ->filter(fn ($permission, $to) => $this->allowed($followUp, $user, S::from($to), $permission))
            ->mapWithKeys(fn ($permission, $to) => [$to => S::from($to)->getLabel()])
            ->all();
    }

    public function transition(FollowUp $followUp, S $to, User $user, ?string $note = null): FollowUp
    {
        $permission = self::TRANSITIONS[$followUp->status->value][$to->value] ?? null;

        if ($permission === null || ! $this->allowed($followUp, $user, $to, $permission)) {
            throw ValidationException::withMessages(['status' => 'Perubahan status dari "'.$followUp->status->getLabel().'" ke "'.$to->getLabel().'" tidak diizinkan untuk Anda.']);
        }

        if ($to === S::Disampaikan && (! $followUp->pic_user_id && ! $followUp->pic_label || ! $followUp->reviewer_user_id || blank($followUp->required_evidence))) {
            throw ValidationException::withMessages(['status' => 'Tindak lanjut harus punya PIC, reviewer, tenggat, dan bukti yang dipersyaratkan sebelum disampaikan.']);
        }

        if ($to === S::Diperiksa && $followUp->evidences()->count() === 0) {
            throw ValidationException::withMessages(['status' => 'Unggah bukti penyelesaian sebelum mengajukan pemeriksaan.']);
        }

        if ($to === S::Ditutup && $followUp->evidences()->where('is_verified', true)->count() === 0) {
            throw ValidationException::withMessages(['status' => 'Penutupan memerlukan minimal satu bukti yang sudah diverifikasi reviewer. Pernyataan "sudah selesai" saja tidak cukup.']);
        }

        if (in_array($to, [S::Dilaksanakan, S::DibukaKembali], true) && $followUp->status === S::Diperiksa && blank($note)) {
            throw ValidationException::withMessages(['note' => 'Tuliskan alasan mengapa bukti belum memadai.']);
        }

        return DB::transaction(function () use ($followUp, $to, $user, $note) {
            $from = $followUp->status;
            $changes = ['status' => $to];

            if ($to === S::Diperiksa) {
                $changes['completed_at'] = now();
            }

            if ($to === S::Ditutup) {
                $changes['closed_at'] = now();
                // Ketepatan waktu dinilai dari tanggal penyelesaian dilaporkan, dibanding tenggat yang berlaku.
                $completed = $followUp->completed_at ?? now();
                $changes['timeliness'] = $completed->copy()->startOfDay()->gt($followUp->target_date) ? 'terlambat' : 'tepat_waktu';
            }

            if ($to === S::DibukaKembali) {
                $changes['closed_at'] = null;
                $changes['timeliness'] = null;
            }

            $followUp->update($changes);
            $this->log($followUp, $user, $from->value, $to->value, $note);

            return $followUp;
        });
    }

    public function verifyEvidence(FollowUp $followUp, int $evidenceId, User $user): void
    {
        if (! $this->isReviewer($followUp, $user)) {
            throw ValidationException::withMessages(['evidence' => 'Hanya reviewer atau Komisaris Utama yang dapat memverifikasi bukti.']);
        }

        $evidence = $followUp->evidences()->findOrFail($evidenceId);
        $evidence->update(['is_verified' => true, 'verified_by' => $user->id, 'verified_at' => now()]);
        $this->log($followUp, $user, null, null, 'Bukti diverifikasi: '.$evidence->description);
    }

    public function requestDeadlineChange(FollowUp $followUp, string $newDate, string $reason, User $user): FollowUpDeadlineChange
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan perubahan tenggat wajib diisi.']);
        }

        $change = $followUp->deadlineChanges()->create([
            'company_id' => $followUp->company_id,
            'old_date' => $followUp->target_date,
            'new_date' => $newDate,
            'reason' => $reason,
            'requested_by' => $user->id,
        ]);

        $this->log($followUp, $user, null, null, 'Pengajuan perubahan tenggat ke '.$change->new_date->format('d/m/Y').': '.$reason);

        return $change;
    }

    public function decideDeadlineChange(FollowUpDeadlineChange $change, bool $approve, User $user): void
    {
        $followUp = FollowUp::findOrFail($change->follow_up_id);

        if (! $user->canIn('tenggat.setujui', $followUp->company_id)) {
            throw ValidationException::withMessages(['deadline' => 'Anda tidak berwenang menyetujui perubahan tenggat.']);
        }

        DB::transaction(function () use ($change, $approve, $user, $followUp) {
            $change->update(['status' => $approve ? 'disetujui' : 'ditolak', 'decided_by' => $user->id, 'decided_at' => now()]);

            if ($approve) {
                $followUp->update(['target_date' => $change->new_date, 'escalation_level' => 0]);
            }

            $this->log($followUp, $user, null, null, 'Perubahan tenggat '.($approve ? 'disetujui' : 'ditolak').'.');
        });
    }

    public function isReviewer(FollowUp $followUp, User $user): bool
    {
        return $followUp->reviewer_user_id === $user->id
            || $user->roleIn($followUp->company_id)?->value === 'komisaris_utama';
    }

    protected function allowed(FollowUp $followUp, User $user, S $to, string $permission): bool
    {
        if (! $user->canIn($permission, $followUp->company_id)) {
            return false;
        }

        // PIC hanya bisa menggerakkan tindak lanjut miliknya sendiri.
        if ($permission === 'tindaklanjut.tanggapi' && $user->roleIn($followUp->company_id)?->value === 'penghubung') {
            return $followUp->pic_user_id === $user->id;
        }

        if ($permission === 'tindaklanjut.periksa') {
            return $this->isReviewer($followUp, $user);
        }

        return true;
    }

    public function log(FollowUp $followUp, ?User $user, ?string $from, ?string $to, ?string $note): void
    {
        FollowUpEvent::withoutGlobalScopes()->create([
            'company_id' => $followUp->company_id,
            'follow_up_id' => $followUp->id,
            'user_id' => $user?->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);

        AuditLog::record('followup.'.($to ?? 'note'), $followUp);
    }
}
