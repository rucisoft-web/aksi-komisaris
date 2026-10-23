<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bahan rapat per agenda. Setelah paket dibekukan, document_version_id terkunci. */
class AgendaMaterial extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['agenda_item_id' => AgendaItem::class, 'document_id' => Document::class, 'document_version_id' => DocumentVersion::class];

    protected function casts(): array
    {
        return ['is_addendum' => 'boolean'];
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** Versi beku bila paket sudah dibekukan, selain itu versi terkini. */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }
}
