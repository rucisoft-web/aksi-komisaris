<?php

namespace App\Models;

use App\Enums\SpaceKind;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Perusahaan (tenant). Seluruh data bisnis berinduk ke sini. */
class Company extends Model implements HasName
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'ai_enabled' => 'boolean',
            'ai_allow_restricted' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            $company->slug ??= Str::slug($company->code ?: $company->name);
        });

        // Struktur awal: organ standar dan ruang resmi dewan. Nama bisa diubah per perusahaan.
        static::created(function (Company $company) {
            $board = $company->organs()->create(['kind' => 'dewan_komisaris', 'name' => 'Dewan Komisaris']);
            $company->organs()->create(['kind' => 'direksi', 'name' => 'Direksi']);
            $company->organs()->create(['kind' => 'komite', 'name' => 'Komite Audit']);
            $company->spaces()->create([
                'kind' => SpaceKind::Dewan,
                'name' => 'Ruang Resmi Dewan Komisaris',
                'organ_id' => $board->id,
            ]);
        });
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function organs(): HasMany
    {
        return $this->hasMany(Organ::class);
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    public function boardSpace(): ?Space
    {
        return Space::withoutGlobalScopes()
            ->where('company_id', $this->id)
            ->where('kind', SpaceKind::Dewan)
            ->orderBy('id')
            ->first();
    }

    // Relasi yang dibutuhkan Filament untuk resource bertenant.
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function documentVersions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function requiredDocuments(): HasMany
    {
        return $this->hasMany(RequiredDocument::class);
    }

    public function requiredDocumentReceipts(): HasMany
    {
        return $this->hasMany(RequiredDocumentReceipt::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(AgendaItem::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function personalNotes(): HasMany
    {
        return $this->hasMany(PersonalNote::class);
    }

    public function informationRequests(): HasMany
    {
        return $this->hasMany(InformationRequest::class);
    }

    public function advices(): HasMany
    {
        return $this->hasMany(Advice::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function aiJobs(): HasMany
    {
        return $this->hasMany(AiJob::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function conflictDeclarations(): HasMany
    {
        return $this->hasMany(ConflictDeclaration::class);
    }
}
