<?php

namespace App\Services\Ai;

use App\Enums\AiPolicy;
use App\Enums\Classification;
use App\Enums\ProcessingStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentVersion;

/**
 * Memutuskan apakah isi sebuah dokumen boleh dikirim ke API eksternal.
 * Mengembalikan null bila boleh, atau alasan penolakan dalam bahasa pengguna.
 */
class AiPolicyGate
{
    public function denialReason(Company $company, Document $document, ?DocumentVersion $version): ?string
    {
        if (! $company->ai_enabled) {
            return 'Pemrosesan AI belum diaktifkan untuk perusahaan ini.';
        }

        if ($document->ai_policy === AiPolicy::Dilarang) {
            return 'Kebijakan dokumen melarang pengiriman ke AI.';
        }

        if ($version !== null && $version->processing_status !== ProcessingStatus::Siap) {
            return 'Hasil ekstraksi belum siap atau perlu ditinjau ('.$version->processing_status->getLabel().').';
        }

        return match ($document->classification) {
            Classification::Publik, Classification::Internal => null,
            Classification::Rahasia => $document->ai_policy === AiPolicy::Khusus
                ? null
                : 'Dokumen rahasia memerlukan izin pemrosesan AI khusus.',
            Classification::SangatTerbatas => ($document->ai_policy === AiPolicy::Khusus && $company->ai_allow_restricted)
                ? null
                : 'Dokumen sangat terbatas tidak dikirim ke API eksternal.',
        };
    }
}
