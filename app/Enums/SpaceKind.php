<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jenis ruang kerja. */
enum SpaceKind: string implements HasColor, HasLabel
{
    case Pribadi = 'pribadi';
    case Dewan = 'dewan';
    case Komite = 'komite';
    case Terbatas = 'terbatas';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pribadi => 'Ruang pribadi',
            self::Dewan => 'Ruang resmi dewan',
            self::Komite => 'Ruang komite',
            self::Terbatas => 'Ruang terbatas',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pribadi => 'gray',
            self::Dewan => 'primary',
            self::Komite => 'info',
            self::Terbatas => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
