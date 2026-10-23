<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Kebijakan pemrosesan AI per dokumen. */
enum AiPolicy: string implements HasColor, HasLabel
{
    case Standar = 'standar';
    case Khusus = 'khusus';
    case Dilarang = 'dilarang';

    public function getLabel(): string
    {
        return match ($this) {
            self::Standar => 'Standar (ikuti klasifikasi)',
            self::Khusus => 'Izin khusus',
            self::Dilarang => 'Dilarang dikirim ke AI',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Standar => 'info',
            self::Khusus => 'warning',
            self::Dilarang => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
