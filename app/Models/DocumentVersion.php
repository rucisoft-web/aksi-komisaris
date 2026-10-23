<?php

namespace App\Models;

use App\Enums\ProcessingStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu versi berkas dokumen. Versi lama tidak pernah dihapus saat versi baru diunggah. */
class DocumentVersion extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['document_id' => Document::class];

    protected function casts(): array
    {
        return ['processing_status' => ProcessingStatus::class];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isUsableForAnalysis(): bool
    {
        return $this->processing_status === ProcessingStatus::Siap;
    }
}
