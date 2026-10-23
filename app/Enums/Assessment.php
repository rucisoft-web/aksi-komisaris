<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Penilaian jawaban. */
enum Assessment: string implements HasColor, HasLabel
{
    case Memadai = 'memadai';
    case Sebagian = 'sebagian';
    case PerluKlarifikasi = 'perlu_klarifikasi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Memadai => 'Memadai',
            self::Sebagian => 'Sebagian memadai',
            self::PerluKlarifikasi => 'Perlu klarifikasi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Memadai => 'success',
            self::Sebagian => 'warning',
            self::PerluKlarifikasi => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
