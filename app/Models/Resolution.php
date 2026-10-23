<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Keputusan atau rekomendasi rapat per agenda. */
class Resolution extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['meeting_id' => Meeting::class, 'agenda_item_id' => AgendaItem::class];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function dissents(): HasMany
    {
        return $this->hasMany(Dissent::class);
    }
}
