<?php

namespace App\Models;

use App\Enums\AiPolicy;
use App\Enums\Classification;
use App\Enums\DocumentCategory;
use App\Enums\DocumentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Dokumen pengawasan. Isi berkas ada di DocumentVersion. */
class Document extends Model
{
    use Auditable, BelongsToCompany, SoftDeletes;

    protected $guarded = ['id'];

    protected array $companyParents = ['space_id' => Space::class];

    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'classification' => Classification::class,
            'ai_policy' => AiPolicy::class,
            'status' => DocumentStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'retain_until' => 'date',
            'legal_hold' => 'boolean',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_no');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(DocumentGrant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function periodLabel(): string
    {
        if (! $this->period_start && ! $this->period_end) {
            return 'Periode tidak diisi';
        }

        return trim(($this->period_start?->format('d/m/Y') ?? '').' s.d. '.($this->period_end?->format('d/m/Y') ?? ''));
    }
}
