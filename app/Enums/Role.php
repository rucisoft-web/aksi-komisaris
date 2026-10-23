<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Peran pengguna dalam satu perusahaan. */
enum Role: string implements HasColor, HasLabel
{
    case KomisarisUtama = 'komisaris_utama';
    case KomisarisIndependen = 'komisaris_independen';
    case Komisaris = 'komisaris';
    case AnggotaKomite = 'anggota_komite';
    case Sekretariat = 'sekretariat';
    case Penghubung = 'penghubung';
    case AdminPerusahaan = 'admin_perusahaan';

    public function getLabel(): string
    {
        return match ($this) {
            self::KomisarisUtama => 'Komisaris Utama',
            self::KomisarisIndependen => 'Komisaris Independen',
            self::Komisaris => 'Anggota Dewan Komisaris',
            self::AnggotaKomite => 'Anggota Komite',
            self::Sekretariat => 'Sekretariat Dewan Komisaris',
            self::Penghubung => 'Direksi / Penghubung Resmi',
            self::AdminPerusahaan => 'Admin Perusahaan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::KomisarisUtama => 'primary',
            self::KomisarisIndependen => 'primary',
            self::Komisaris => 'primary',
            self::AnggotaKomite => 'info',
            self::Sekretariat => 'warning',
            self::Penghubung => 'gray',
            self::AdminPerusahaan => 'gray',
        };
    }

    public function can(string $permission): bool
    {
        return in_array($permission, config('aksi.permissions.'.$this->value, []), true);
    }

    public function isCommissioner(): bool
    {
        return in_array($this, [self::KomisarisUtama, self::KomisarisIndependen, self::Komisaris], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
