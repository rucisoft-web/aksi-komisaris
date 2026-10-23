<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Perbedaan pendapat anggota atas sebuah keputusan. */
class Dissent extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['resolution_id' => Resolution::class];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
