<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Mencatat pembuatan, perubahan, dan penghapusan ke audit_logs.
 *
 * Hanya nama kolom yang berubah yang dicatat, bukan isinya, agar
 * log audit tidak menjadi jalan belakang untuk membaca isi rahasia.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => AuditLog::record('created', $model));
        static::updated(fn (Model $model) => AuditLog::record('updated', $model, [
            'fields' => array_values(array_diff(array_keys($model->getChanges()), ['updated_at'])),
        ]));
        static::deleted(fn (Model $model) => AuditLog::record('deleted', $model));
    }
}
