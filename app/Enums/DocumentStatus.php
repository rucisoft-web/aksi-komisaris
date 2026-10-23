<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status dokumen. */
enum DocumentStatus: string implements HasColor, HasLabel
{
    case Draf = 'draf';
    case Final = 'final';
    case Disetujui = 'disetujui';
    case Digantikan = 'digantikan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Final => 'Final',
            self::Disetujui => 'Disetujui',
            self::Digantikan => 'Digantikan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draf => 'gray',
            self::Final => 'info',
            self::Disetujui => 'success',
            self::Digantikan => 'warning',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
