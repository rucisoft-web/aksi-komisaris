<?php

namespace App\Support;

use App\Models\Company;

/**
 * Perusahaan aktif untuk permintaan atau job yang sedang berjalan.
 *
 * Diisi oleh middleware tenant Filament dan oleh job antrean sebelum bekerja,
 * lalu dipakai global scope BelongsToCompany.
 */
class CurrentCompany
{
    protected ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->getKey();
    }

    /**
     * Jalankan callback dalam konteks perusahaan tertentu, lalu kembalikan konteks semula.
     */
    public function runAs(Company $company, callable $callback): mixed
    {
        $previous = $this->company;
        $this->company = $company;

        try {
            return $callback();
        } finally {
            $this->company = $previous;
        }
    }
}
