<?php

namespace App\Support;

use App\Enums\Classification;
use App\Enums\SpaceKind;
use App\Models\AgendaMaterial;
use App\Models\Company;
use App\Models\ConflictDeclaration;
use App\Models\Document;
use App\Models\DocumentGrant;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu-satunya tempat keputusan "boleh membaca" dibuat.
 *
 * Dipakai oleh resource Filament, pencarian, layanan AI, unduhan, dan job.
 * Urutan: keanggotaan aktif -> ruang -> klasifikasi -> benturan kepentingan.
 * Larangan selalu menang; administrator tidak mendapat pengecualian.
 */
class AccessGate
{
    /** @var array<string, array<int>> */
    protected array $cache = [];

    /** @return array<int> */
    public function visibleSpaceIds(User $user, Company $company): array
    {
        return $this->cache['spaces:'.$user->id.':'.$company->id] ??= $this->resolveSpaceIds($user, $company);
    }

    /** @return array<int> */
    protected function resolveSpaceIds(User $user, Company $company): array
    {
        $role = $user->roleIn($company);

        if ($role === null) {
            return [];
        }

        $ids = Space::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where(function (Builder $query) use ($user, $role) {
                $query->where(fn (Builder $q) => $q->where('kind', SpaceKind::Pribadi->value)->where('owner_user_id', $user->id));

                if (in_array($role->value, config('aksi.board_space_roles'), true)) {
                    $query->orWhere('kind', SpaceKind::Dewan->value);
                }

                $query->orWhere(fn (Builder $q) => $q
                    ->whereIn('kind', [SpaceKind::Komite->value, SpaceKind::Terbatas->value, SpaceKind::Dewan->value])
                    ->whereHas('members', fn (Builder $m) => $m
                        ->where('users.id', $user->id)
                        ->where(fn (Builder $v) => $v->whereNull('space_members.valid_until')->orWhere('space_members.valid_until', '>=', now()->toDateString()))));
            })
            ->pluck('id')
            ->all();

        return array_values(array_unique($ids));
    }

    public function canViewSpace(User $user, Space $space): bool
    {
        return in_array($space->id, $this->visibleSpaceIds($user, $space->company), true);
    }

    /** Ruang resmi (bukan pribadi) yang boleh dipakai pengguna untuk menulis. */
    public function writableOfficialSpaces(User $user, Company $company): array
    {
        return Space::withoutGlobalScopes()
            ->whereIn('id', $this->visibleSpaceIds($user, $company))
            ->where('kind', '!=', SpaceKind::Pribadi->value)
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int> dokumen yang tertutup karena pengguna mengundurkan diri dari pembahasan. */
    public function recusedDocumentIds(User $user, Company $company): array
    {
        return $this->cache['recused:'.$user->id.':'.$company->id] ??= AgendaMaterial::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('agenda_item_id', ConflictDeclaration::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->where('recusal', true)
                ->whereNotNull('agenda_item_id')
                ->select('agenda_item_id'))
            ->pluck('document_id')
            ->all();
    }

    /**
     * Batasi query dokumen ke yang boleh dibaca pengguna.
     */
    public function scopeDocuments(Builder $query, User $user, Company $company): Builder
    {
        $table = $query->getModel()->getTable();
        $documentColumn = $table === 'documents' ? 'documents.id' : $table.'.document_id';
        $spaceIds = $this->visibleSpaceIds($user, $company);

        $grantIds = DocumentGrant::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString()))
            ->pluck('document_id')
            ->all();

        $query->where($table.'.company_id', $company->id);

        if ($table === 'documents') {
            $query->whereNull('documents.deleted_at')->where(function (Builder $q) use ($spaceIds, $user) {
                $q->whereIn('documents.space_id', $spaceIds)->orWhere('documents.created_by', $user->id);
            })->where(function (Builder $q) use ($grantIds, $user) {
                $q->where('documents.classification', '!=', Classification::SangatTerbatas->value)
                    ->orWhereIn('documents.id', $grantIds)
                    ->orWhere('documents.created_by', $user->id);
            });
        } else {
            $query->whereIn($documentColumn, $this->scopeDocuments(Document::query()->withoutGlobalScopes(), $user, $company)->select('documents.id'));
        }

        $recused = $this->recusedDocumentIds($user, $company);

        if ($recused !== []) {
            $query->whereNotIn($documentColumn, $recused);
        }

        return $query;
    }

    public function canViewDocument(User $user, Document $document): bool
    {
        return $this->scopeDocuments(Document::query()->withoutGlobalScopes(), $user, $document->company)
            ->whereKey($document->id)
            ->exists();
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
