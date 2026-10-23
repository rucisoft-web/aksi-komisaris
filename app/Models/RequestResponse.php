<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jawaban manajemen atas permintaan informasi. */
class RequestResponse extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['information_request_id' => InformationRequest::class, 'document_id' => Document::class];

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responder_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
