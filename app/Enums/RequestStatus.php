<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status permintaan informasi. */
enum RequestStatus: string implements HasColor, HasLabel
{
    case Draf = 'draf';
    case Diajukan = 'diajukan';
    case Dikirim = 'dikirim';
    case Dijawab = 'dijawab';
    case Dinilai = 'dinilai';
    case Ditutup = 'ditutup';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Diajukan => 'Diajukan',
            self::Dikirim => 'Dikirim ke penerima',
            self::Dijawab => 'Dijawab',
            self::Dinilai => 'Dinilai',
            self::Ditutup => 'Ditutup',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draf => 'gray',
            self::Diajukan => 'info',
            self::Dikirim => 'primary',
            self::Dijawab => 'warning',
            self::Dinilai => 'info',
            self::Ditutup => 'success',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
