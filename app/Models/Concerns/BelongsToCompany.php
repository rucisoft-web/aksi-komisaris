<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Isolasi data per perusahaan.
 *
 * - Global scope membatasi query ke perusahaan aktif.
 * - company_id diisi otomatis saat membuat baris.
 * - Baris anak ditolak bila company_id-nya berbeda dari baris induk
 *   yang dirujuk (daftar di $companyParents: kolom => kelas model).
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $companyId = app(CurrentCompany::class)->id();

            if ($companyId !== null) {
                $builder->where($builder->getModel()->qualifyColumn('company_id'), $companyId);
            }
        });

        // Event saving berjalan sebelum creating, jadi company_id diisi di sini.
        static::saving(function (Model $model) {
            if (! $model->company_id) {
                $model->company_id = app(CurrentCompany::class)->id()
                    ?? throw new LogicException('company_id wajib diisi untuk '.class_basename($model).'.');
            }

            foreach ($model->companyParents ?? [] as $column => $class) {
                $parentId = $model->getAttribute($column);

                if ($parentId === null) {
                    continue;
                }

                $parentCompany = $class::withoutGlobalScopes()->whereKey($parentId)->value('company_id');

                if ((int) $parentCompany !== (int) $model->company_id) {
                    throw new LogicException(class_basename($model)." tidak boleh merujuk {$column} milik perusahaan lain.");
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
