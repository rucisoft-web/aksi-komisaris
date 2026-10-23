<?php

namespace App\Models;

use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jejak audit append-only: siapa melakukan apa terhadap objek apa. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public static function record(string $action, ?Model $subject = null, array $meta = []): void
    {
        static::query()->create([
            'company_id' => $subject?->getAttribute('company_id') ?? app(CurrentCompany::class)->id(),
            'user_id' => auth()->id(),
            'action' => $subject ? class_basename($subject).'.'.$action : $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
