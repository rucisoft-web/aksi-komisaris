<?php

namespace App\Models;

use App\Enums\EvidenceStatus;
use App\Enums\IssueStatus;
use App\Enums\Materiality;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Isu pengawasan yang dipantau lintas modul. */
class Issue extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['space_id' => Space::class];

    protected function casts(): array
    {
        return [
            'materiality' => Materiality::class,
            'evidence_status' => EvidenceStatus::class,
            'status' => IssueStatus::class,
            'data_period_end' => 'date',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(InformationRequest::class);
    }
}
