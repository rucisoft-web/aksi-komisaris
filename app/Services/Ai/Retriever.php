<?php

namespace App\Services\Ai;

use App\Models\Company;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Support\AccessGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pencarian potongan dokumen yang boleh dibaca pengguna.
 * MySQL memakai FULLTEXT; driver lain memakai pencocokan kata kunci.
 */
class Retriever
{
    protected const STOPWORDS = ['yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'dengan', 'pada', 'ini', 'itu', 'atau', 'apa', 'adalah', 'dalam', 'akan', 'tidak', 'the', 'and', 'of', 'to', 'saya', 'kami', 'tolong', 'berikan', 'jelaskan'];

    public function __construct(protected AccessGate $gate) {}

    public function baseQuery(User $user, Company $company): Builder
    {
        $query = DocumentChunk::withoutGlobalScopes()
            ->with(['document' => fn ($q) => $q->withoutGlobalScopes(), 'version' => fn ($q) => $q->withoutGlobalScopes()])
            // Hanya versi terkini dokumen, kecuali pemanggil meminta versi tertentu.
            ->whereIn('document_version_id', DB::table('documents')->whereNotNull('current_version_id')->select('current_version_id'));

        return $this->gate->scopeDocuments($query, $user, $company);
    }

    /**
     * @param  array<int>|null  $documentIds
     * @return Collection<int, DocumentChunk>
     */
    public function search(User $user, Company $company, string $text, ?array $documentIds = null, int $limit = 12): Collection
    {
        $query = $this->baseQuery($user, $company);

        if ($documentIds !== null) {
            $query->whereIn('document_chunks.document_id', $documentIds);
        }

        $terms = $this->terms($text);

        if ($terms === []) {
            return $query->orderBy('document_chunks.id')->limit($limit)->get();
        }

        if (DB::getDriverName() === 'mysql') {
            return $query->whereFullText('document_chunks.content', implode(' ', $terms))->limit($limit)->get();
        }

        return $query
            ->where(function (Builder $q) use ($terms) {
                foreach ($terms as $term) {
                    $q->orWhere('document_chunks.content', 'like', '%'.$term.'%');
                }
            })
            ->limit($limit * 4)
            ->get()
            ->sortByDesc(fn (DocumentChunk $chunk) => collect($terms)->sum(fn ($term) => substr_count(mb_strtolower($chunk->content), $term)))
            ->take($limit)
            ->values();
    }

    /** @return array<int, string> */
    public function terms(string $text): array
    {
        $words = preg_split('/[^\pL\pN]+/u', mb_strtolower($text)) ?: [];

        return array_values(array_unique(array_filter($words, fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOPWORDS, true))));
    }
}
