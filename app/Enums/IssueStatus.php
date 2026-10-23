<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status isu pengawasan. */
enum IssueStatus: string implements HasColor, HasLabel
{
    case Dibuka = 'dibuka';
    case Dipantau = 'dipantau';
    case MenungguManajemen = 'menunggu_manajemen';
    case Selesai = 'selesai';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dibuka => 'Dibuka',
            self::Dipantau => 'Dipantau',
            self::MenungguManajemen => 'Menunggu manajemen',
            self::Selesai => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Dibuka => 'danger',
            self::Dipantau => 'warning',
            self::MenungguManajemen => 'info',
            self::Selesai => 'success',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
