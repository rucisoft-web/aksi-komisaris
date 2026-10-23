<?php

namespace App\Services;

use App\Enums\ProcessingStatus;
use App\Jobs\ProcessDocumentVersion;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    public function __construct(protected TextExtractor $extractor) {}

    /**
     * Simpan berkas sebagai versi baru. Versi lama tetap ada dan tidak berubah.
     *
     * @param  string  $sourcePath  path absolut berkas sementara
     */
    public function addVersion(Document $document, string $sourcePath, string $originalName, ?User $user, ?string $changeNote = null): DocumentVersion
    {
        $disk = config('aksi.uploads.disk');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $version = DB::transaction(function () use ($document, $sourcePath, $originalName, $user, $changeNote, $disk, $extension) {
            $number = (int) DocumentVersion::withoutGlobalScopes()->where('document_id', $document->id)->max('version_no') + 1;
            $storedPath = sprintf('company/%d/documents/%d/v%d-%s.%s', $document->company_id, $document->id, $number, Str::random(12), $extension);

            Storage::disk($disk)->put($storedPath, fopen($sourcePath, 'r'));

            $version = DocumentVersion::withoutGlobalScopes()->create([
                'company_id' => $document->company_id,
                'document_id' => $document->id,
                'version_no' => $number,
                'disk_path' => $storedPath,
                'original_name' => $originalName,
                'mime' => mime_content_type($sourcePath) ?: null,
                'size' => filesize($sourcePath) ?: 0,
                'sha256' => hash_file('sha256', $sourcePath),
                'processing_status' => ProcessingStatus::Antre,
                'change_note' => $changeNote,
                'uploaded_by' => $user?->id,
            ]);

            $document->forceFill(['current_version_id' => $version->id])->saveQuietly();

            return $version;
        });

        ProcessDocumentVersion::dispatch($version->id);

        return $version;
    }

    /** Ekstraksi teks dan pemotongan menjadi chunk dengan rujukan lokasi. */
    public function process(DocumentVersion $version): void
    {
        $version->update(['processing_status' => ProcessingStatus::Diproses]);
        $document = $version->document()->withoutGlobalScopes()->firstOrFail();
        $absolute = Storage::disk(config('aksi.uploads.disk'))->path($version->disk_path);
        $result = $this->extractor->extract($absolute, pathinfo($version->original_name, PATHINFO_EXTENSION));

        DB::transaction(function () use ($version, $document, $result) {
            DocumentChunk::withoutGlobalScopes()->where('document_version_id', $version->id)->delete();
            $seq = 0;

            foreach ($result['segments'] as $segment) {
                foreach ($this->split($segment['text']) as $piece) {
                    DocumentChunk::withoutGlobalScopes()->create([
                        'company_id' => $version->company_id,
                        'document_id' => $document->id,
                        'document_version_id' => $version->id,
                        'space_id' => $document->space_id,
                        'classification' => $document->classification,
                        'page_no' => $segment['page_no'],
                        'sheet' => $segment['sheet'],
                        'cell_range' => $segment['cell_range'],
                        'seq' => ++$seq,
                        'content' => $piece,
                    ]);
                }
            }

            $version->update([
                'processing_status' => $result['quality'] === 'baik' ? ProcessingStatus::Siap : ProcessingStatus::PerluTinjauan,
                'text_quality' => $result['quality'],
                'page_count' => $result['page_count'],
                'processing_note' => $result['note'],
            ]);
        });
    }

    /** Pengguna berwenang mengonfirmasi hasil ekstraksi berkualitas rendah sebelum dipakai analisis. */
    public function confirm(DocumentVersion $version, User $user): void
    {
        $version->update(['processing_status' => ProcessingStatus::Siap, 'confirmed_by' => $user->id]);
        AuditLog::record('extraction_confirmed', $version);
    }

    /** Selaraskan salinan klasifikasi/ruang pada chunk ketika metadata dokumen berubah. */
    public function syncChunkMetadata(Document $document): void
    {
        DocumentChunk::withoutGlobalScopes()->where('document_id', $document->id)->update([
            'classification' => $document->classification->value,
            'space_id' => $document->space_id,
        ]);
    }

    /** @return array<int, string> */
    protected function split(string $text): array
    {
        $limit = (int) config('aksi.extraction.chunk_chars');

        if (mb_strlen($text) <= $limit) {
            return [$text];
        }

        $pieces = [];
        $current = '';

        foreach (preg_split("/\n{2,}/u", $text) ?: [$text] as $paragraph) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($paragraph) > $limit) {
                $pieces[] = $current;
                $current = '';
            }

            while (mb_strlen($paragraph) > $limit) {
                $pieces[] = mb_substr($paragraph, 0, $limit);
                $paragraph = mb_substr($paragraph, $limit);
            }

            $current = trim($current."\n\n".$paragraph);
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }
}
