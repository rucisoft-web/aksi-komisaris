<?php

namespace App\Models;

use App\Enums\Assessment;
use App\Enums\RequestStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Permintaan informasi resmi dari dewan kepada manajemen. */
class InformationRequest extends Model
{
    use Auditable, BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['space_id' => Space::class, 'parent_id' => InformationRequest::class, 'issue_id' => Issue::class, 'meeting_id' => Meeting::class];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'assessment' => Assessment::class,
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'answered_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(InformationRequest::class, 'parent_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(RequestResponse::class)->latest();
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && in_array($this->status, [RequestStatus::Dikirim], true)
            && $this->due_date->isPast();
    }
}
