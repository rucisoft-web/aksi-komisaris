<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penerimaan dokumen wajib per periode. */
class RequiredDocumentReceipt extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['required_document_id' => RequiredDocument::class, 'document_id' => Document::class];

    protected function casts(): array
    {
        return ['period_end' => 'date', 'due_date' => 'date', 'received_at' => 'datetime'];
    }

    public function requiredDocument(): BelongsTo
    {
        return $this->belongsTo(RequiredDocument::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
