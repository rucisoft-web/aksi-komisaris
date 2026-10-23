<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Organ perusahaan (dewan komisaris, direksi, komite). Nama mengikuti perusahaan. */
class Organ extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }
}
