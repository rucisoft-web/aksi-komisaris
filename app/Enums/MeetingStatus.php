<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status rapat. */
enum MeetingStatus: string implements HasColor, HasLabel
{
    case Dijadwalkan = 'dijadwalkan';
    case PaketDibekukan = 'paket_dibekukan';
    case Selesai = 'selesai';
    case RisalahDisahkan = 'risalah_disahkan';
    case Batal = 'batal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dijadwalkan => 'Dijadwalkan',
            self::PaketDibekukan => 'Paket dibekukan',
            self::Selesai => 'Selesai',
            self::RisalahDisahkan => 'Risalah disahkan',
            self::Batal => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Dijadwalkan => 'gray',
            self::PaketDibekukan => 'info',
            self::Selesai => 'warning',
            self::RisalahDisahkan => 'success',
            self::Batal => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
