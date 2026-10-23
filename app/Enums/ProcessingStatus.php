<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status pemrosesan versi dokumen. */
enum ProcessingStatus: string implements HasColor, HasLabel
{
    case Antre = 'antre';
    case Diproses = 'diproses';
    case Siap = 'siap';
    case PerluTinjauan = 'perlu_tinjauan';
    case Gagal = 'gagal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Antre => 'Menunggu diproses',
            self::Diproses => 'Sedang diproses',
            self::Siap => 'Siap',
            self::PerluTinjauan => 'Perlu tinjauan',
            self::Gagal => 'Gagal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Antre => 'gray',
            self::Diproses => 'info',
            self::Siap => 'success',
            self::PerluTinjauan => 'warning',
            self::Gagal => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
