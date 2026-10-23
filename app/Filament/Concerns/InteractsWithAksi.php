<?php

namespace App\Filament\Concerns;

use App\Models\Company;
use App\Models\Membership;
use App\Models\Space;
use App\Models\User;
use App\Support\AccessGate;
use Filament\Facades\Filament;

/**
 * Pintasan untuk resource dan halaman: pengguna, perusahaan aktif, izin.
 */
trait InteractsWithAksi
{
    protected static function aksiUser(): User
    {
        /** @var User */
        return auth()->user();
    }

    protected static function aksiCompany(): Company
    {
        /** @var Company */
        return Filament::getTenant();
    }

    protected static function aksiCan(string $permission): bool
    {
        $tenant = Filament::getTenant();

        return $tenant !== null && static::aksiUser()->canIn($permission, $tenant);
    }

    protected static function gate(): AccessGate
    {
        return app(AccessGate::class);
    }

    /** @return array<int> */
    protected static function visibleSpaceIds(): array
    {
        return static::gate()->visibleSpaceIds(static::aksiUser(), static::aksiCompany());
    }

    /** @return array<int, string> ruang yang bisa dipilih sebagai lokasi penyimpanan. */
    protected static function spaceOptions(bool $includePrivate = true): array
    {
        $company = static::aksiCompany();
        $user = static::aksiUser();
        $query = Space::query()->whereIn('id', static::visibleSpaceIds());

        if (! $includePrivate) {
            $query->where('kind', '!=', 'pribadi');
        }

        $options = $query->orderBy('kind')->pluck('name', 'id')->all();

        // Penghubung tidak membaca ruang dewan, tetapi boleh menyerahkan dokumen ke sana.
        if ($options === [] && $user->canIn('dokumen.unggah', $company) && ($board = $company->boardSpace())) {
            $options = [$board->id => $board->name.' (serahkan)'];
        }

        return $options;
    }

    /** @return array<int, string> pengguna aktif di perusahaan ini, opsional difilter peran. */
    protected static function memberOptions(?array $roles = null): array
    {
        return Membership::query()
            ->with('user')
            ->where('is_active', true)
            ->when($roles, fn ($q) => $q->whereIn('role', $roles))
            ->get()
            ->filter(fn ($m) => $m->isCurrentlyValid())
            ->mapWithKeys(fn ($m) => [$m->user_id => $m->user->name.' ('.($m->position_title ?: $m->role->getLabel()).')'])
            ->all();
    }
}
