<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status nasihat. */
enum AdviceStatus: string implements HasColor, HasLabel
{
    case Draf = 'draf';
    case Disahkan = 'disahkan';
    case Disampaikan = 'disampaikan';
    case Ditanggapi = 'ditanggapi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Disahkan => 'Disahkan',
            self::Disampaikan => 'Disampaikan',
            self::Ditanggapi => 'Ditanggapi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draf => 'gray',
            self::Disahkan => 'info',
            self::Disampaikan => 'primary',
            self::Ditanggapi => 'success',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
