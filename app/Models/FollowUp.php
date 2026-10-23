<?php

namespace App\Models;

use App\Enums\FollowUpStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Tindak lanjut atas keputusan, nasihat, atau permintaan informasi. */
class FollowUp extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => FollowUpStatus::class,
            'original_target_date' => 'date',
            'target_date' => 'date',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(FollowUpEvidence::class);
    }

    public function deadlineChanges(): HasMany
    {
        return $this->hasMany(FollowUpDeadlineChange::class)->latest();
    }

    public function events(): HasMany
    {
        return $this->hasMany(FollowUpEvent::class)->latest();
    }

    public function isOpen(): bool
    {
        return $this->status !== FollowUpStatus::Ditutup;
    }

    /** Ketepatan waktu dinilai terpisah dari status pelaksanaan. */
    public function timelinessLabel(): string
    {
        if ($this->timeliness) {
            return $this->timeliness === 'terlambat' ? 'Selesai terlambat' : 'Tepat waktu';
        }

        if ($this->isOpen() && $this->target_date->lt(now()->startOfDay())) {
            return 'Lewat tenggat';
        }

        if ($this->isOpen() && $this->target_date->lte(now()->addDays(7))) {
            return 'Mendekati tenggat';
        }

        return 'Dalam jadwal';
    }

    public function sourceLabel(): string
    {
        return match ($this->source_type) {
            Resolution::class => 'Keputusan rapat',
            Advice::class => 'Nasihat/rekomendasi',
            InformationRequest::class => 'Permintaan informasi',
            default => 'Lainnya',
        };
    }
}
