<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pengajuan perubahan tenggat beserta alasan dan keputusan. */
class FollowUpDeadlineChange extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['follow_up_id' => FollowUp::class];

    protected function casts(): array
    {
        return ['old_date' => 'date', 'new_date' => 'date', 'decided_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
