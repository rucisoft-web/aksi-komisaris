<?php

namespace App\Models;

use App\Enums\Classification;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Potongan teks dokumen dengan rujukan halaman/lembar untuk pencarian dan AI. */
class DocumentChunk extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['document_version_id' => DocumentVersion::class];

    protected function casts(): array
    {
        return ['classification' => Classification::class];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function locationLabel(): string
    {
        if ($this->sheet) {
            return 'lembar '.$this->sheet.($this->cell_range ? ', sel '.$this->cell_range : '');
        }

        return $this->page_no ? 'hal. '.$this->page_no : 'bagian '.$this->seq;
    }
}
