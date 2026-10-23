<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status bukti sebuah klaim. */
enum EvidenceStatus: string implements HasColor, HasLabel
{
    case FaktaTerverifikasi = 'fakta_terverifikasi';
    case PenjelasanManajemen = 'penjelasan_manajemen';
    case Indikasi = 'indikasi';
    case Dugaan = 'dugaan';
    case KesimpulanResmi = 'kesimpulan_resmi';

    public function getLabel(): string
    {
        return match ($this) {
            self::FaktaTerverifikasi => 'Fakta terverifikasi',
            self::PenjelasanManajemen => 'Penjelasan manajemen',
            self::Indikasi => 'Indikasi',
            self::Dugaan => 'Dugaan',
            self::KesimpulanResmi => 'Kesimpulan resmi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::FaktaTerverifikasi => 'success',
            self::PenjelasanManajemen => 'info',
            self::Indikasi => 'warning',
            self::Dugaan => 'danger',
            self::KesimpulanResmi => 'primary',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
