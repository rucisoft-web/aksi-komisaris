<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Dokumen yang wajib diserahkan manajemen secara berkala. */
class RequiredDocument extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['category' => DocumentCategory::class, 'is_active' => 'boolean'];
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(RequiredDocumentReceipt::class);
    }
}
