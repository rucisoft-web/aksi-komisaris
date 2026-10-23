<?php

namespace App\Models;

use App\Enums\AdviceKind;
use App\Enums\AdviceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Catatan pengawasan, nasihat, rekomendasi, atau persetujuan dewan. */
class Advice extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected $table = 'advices';

    protected array $companyParents = ['space_id' => Space::class, 'meeting_id' => Meeting::class];

    protected function casts(): array
    {
        return [
            'kind' => AdviceKind::class,
            'status' => AdviceStatus::class,
            'approved_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function followUps(): MorphMany
    {
        return $this->morphMany(FollowUp::class, 'source');
    }
}
