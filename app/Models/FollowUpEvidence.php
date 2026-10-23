<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bukti penyelesaian tindak lanjut. */
class FollowUpEvidence extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected $table = 'follow_up_evidences';

    protected array $companyParents = ['follow_up_id' => FollowUp::class];

    protected function casts(): array
    {
        return ['is_verified' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
