<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Butir agenda rapat. */
class AgendaItem extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['meeting_id' => Meeting::class];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(AgendaMaterial::class);
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(ConflictDeclaration::class);
    }
}
