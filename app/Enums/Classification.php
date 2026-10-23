<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Klasifikasi kerahasiaan dokumen. */
enum Classification: string implements HasColor, HasLabel
{
    case Publik = 'publik';
    case Internal = 'internal';
    case Rahasia = 'rahasia';
    case SangatTerbatas = 'sangat_terbatas';

    public function getLabel(): string
    {
        return match ($this) {
            self::Publik => 'Publik',
            self::Internal => 'Internal',
            self::Rahasia => 'Rahasia',
            self::SangatTerbatas => 'Sangat terbatas',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Publik => 'success',
            self::Internal => 'info',
            self::Rahasia => 'warning',
            self::SangatTerbatas => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
