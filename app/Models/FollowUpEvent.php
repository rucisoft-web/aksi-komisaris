<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat perubahan status dan eskalasi tindak lanjut. */
class FollowUpEvent extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['follow_up_id' => FollowUp::class];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
