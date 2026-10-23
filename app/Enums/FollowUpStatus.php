<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status pelaksanaan tindak lanjut. */
enum FollowUpStatus: string implements HasColor, HasLabel
{
    case Draf = 'draf';
    case Disahkan = 'disahkan';
    case Disampaikan = 'disampaikan';
    case Ditanggapi = 'ditanggapi';
    case Dilaksanakan = 'dilaksanakan';
    case Diperiksa = 'diperiksa';
    case Ditutup = 'ditutup';
    case DibukaKembali = 'dibuka_kembali';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Disahkan => 'Disahkan',
            self::Disampaikan => 'Disampaikan',
            self::Ditanggapi => 'Ditanggapi',
            self::Dilaksanakan => 'Dilaksanakan',
            self::Diperiksa => 'Diperiksa',
            self::Ditutup => 'Ditutup',
            self::DibukaKembali => 'Dibuka kembali',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draf => 'gray',
            self::Disahkan => 'info',
            self::Disampaikan => 'info',
            self::Ditanggapi => 'primary',
            self::Dilaksanakan => 'warning',
            self::Diperiksa => 'warning',
            self::Ditutup => 'success',
            self::DibukaKembali => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
