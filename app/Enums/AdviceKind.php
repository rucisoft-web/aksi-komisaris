<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jenis keluaran pengawasan. */
enum AdviceKind: string implements HasColor, HasLabel
{
    case CatatanPengawasan = 'catatan_pengawasan';
    case Nasihat = 'nasihat';
    case Rekomendasi = 'rekomendasi';
    case Persetujuan = 'persetujuan';

    public function getLabel(): string
    {
        return match ($this) {
            self::CatatanPengawasan => 'Catatan pengawasan',
            self::Nasihat => 'Nasihat',
            self::Rekomendasi => 'Rekomendasi',
            self::Persetujuan => 'Persetujuan sesuai kewenangan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CatatanPengawasan => 'gray',
            self::Nasihat => 'info',
            self::Rekomendasi => 'primary',
            self::Persetujuan => 'success',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
