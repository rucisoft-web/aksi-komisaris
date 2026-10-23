<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu permintaan ke asisten AI beserta sumber, hasil, dan validasinya. */
class AiJob extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'sources' => 'array',
            'excluded_sources' => 'array',
            'calculations' => 'array',
            'tool_calls' => 'array',
            'output' => 'array',
            'validation' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
