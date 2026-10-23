<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Kelompok dokumen pengawasan. */
enum DocumentCategory: string implements HasColor, HasLabel
{
    case Strategi = 'strategi';
    case Perencanaan = 'perencanaan';
    case Kinerja = 'kinerja';
    case Keuangan = 'keuangan';
    case Audit = 'audit';
    case Risiko = 'risiko';
    case Korporasi = 'korporasi';
    case TataKelola = 'tata_kelola';
    case Pengawasan = 'pengawasan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Strategi => 'Strategi',
            self::Perencanaan => 'Perencanaan (RKAP, anggaran, KPI)',
            self::Kinerja => 'Laporan kinerja',
            self::Keuangan => 'Laporan keuangan',
            self::Audit => 'Audit (eksternal, SPI)',
            self::Risiko => 'Risiko',
            self::Korporasi => 'Aksi korporasi',
            self::TataKelola => 'Tata kelola',
            self::Pengawasan => 'Pengawasan (risalah, nasihat, jawaban)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Strategi => 'gray',
            self::Perencanaan => 'gray',
            self::Kinerja => 'gray',
            self::Keuangan => 'gray',
            self::Audit => 'gray',
            self::Risiko => 'gray',
            self::Korporasi => 'gray',
            self::TataKelola => 'gray',
            self::Pengawasan => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
