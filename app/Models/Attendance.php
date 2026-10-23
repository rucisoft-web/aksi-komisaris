<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kehadiran rapat. */
class Attendance extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['meeting_id' => Meeting::class];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
