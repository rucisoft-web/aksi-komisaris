<?php

namespace App\Models;

use App\Enums\SpaceKind;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ruang kerja: unit kerahasiaan (pribadi, dewan, komite, terbatas). */
class Space extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['kind' => SpaceKind::class];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function organ(): BelongsTo
    {
        return $this->belongsTo(Organ::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'space_members')->withPivot(['access', 'valid_until'])->withTimestamps();
    }

    public function spaceMembers(): HasMany
    {
        return $this->hasMany(SpaceMember::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
