<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\SpaceKind;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Keanggotaan pengguna di satu perusahaan, terikat masa jabatan. */
class Membership extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
            'is_independent' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Pengguna dewan dan komite mendapat ruang pribadi sendiri di perusahaan ini.
        static::saved(function (Membership $membership) {
            if (! in_array($membership->role->value, config('aksi.private_space_roles'), true)) {
                return;
            }

            Space::withoutGlobalScopes()->firstOrCreate(
                ['company_id' => $membership->company_id, 'kind' => SpaceKind::Pribadi->value, 'owner_user_id' => $membership->user_id],
                ['name' => 'Ruang pribadi '.$membership->user->name],
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCurrentlyValid(): bool
    {
        $today = now()->startOfDay();

        return $this->is_active
            && $this->valid_from->lte($today)
            && ($this->valid_until === null || $this->valid_until->gte($today));
    }
}
