<?php

namespace App\Services\Ai;

/**
 * Memeriksa keluaran model sebelum ditampilkan.
 *
 * - Rujukan ke ID sumber yang tidak dikirim dihapus dan dihitung sebagai rujukan palsu.
 * - Kutipan harus benar-benar ada di teks sumber.
 * - Fakta/penjelasan manajemen tanpa kutipan valid ditandai "tidak terverifikasi".
 * - Hasil hitung harus merujuk kalkulasi aplikasi (C*).
 * - Angka di analisis yang tidak ada di sumber/kalkulasi ditandai.
 */
class OutputValidator
{
    /**
     * @param  array<string, array>  $sources  ID => sumber (dengan kunci "text")
     * @param  array<string, array>  $calculations  ID => hasil kalkulasi
     * @return array{output: array, report: array}
     */
    public function validate(array $output, array $sources, array $calculations): array
    {
        $report = ['fabricated_citations' => 0, 'unverified_items' => 0, 'unverified_numbers' => 0, 'checked_items' => 0];

        foreach ($output['items'] ?? [] as $index => $item) {
            $report['checked_items']++;
            $flags = [];
            $validCitations = [];

            foreach ($item['citations'] ?? [] as $citation) {
                $source = $sources[$citation['source_id'] ?? ''] ?? null;

                if ($source === null) {
                    $report['fabricated_citations']++;

                    continue;
                }

                $citation['verified'] = $this->quoteFound((string) ($citation['quote'] ?? ''), $source['text']);
                $validCitations[] = $citation;
            }

            $item['citations'] = $validCitations;
            $hasVerifiedQuote = collect($validCitations)->contains('verified', true);

            if (in_array($item['kind'] ?? '', ['fact', 'management_explanation'], true) && ! $hasVerifiedQuote) {
                $flags[] = 'Rujukan tidak terverifikasi: kutipan tidak ditemukan di sumber.';
            }

            if (($item['kind'] ?? '') === 'calculation') {
                $item['calc_ids'] = array_values(array_filter($item['calc_ids'] ?? [], fn ($id) => isset($calculations[$id])));

                if ($item['calc_ids'] === []) {
                    $flags[] = 'Hasil hitung tidak berasal dari mesin kalkulasi aplikasi.';
                }
            }

            if (in_array($item['kind'] ?? '', ['analysis', 'fact', 'proposed_action'], true)) {
                $unknown = $this->unsupportedNumbers((string) $item['text'], $validCitations, $sources, $item['calc_ids'] ?? [], $calculations);

                if ($unknown !== []) {
                    $report['unverified_numbers'] += count($unknown);
                    $flags[] = 'Angka tidak terverifikasi: '.implode(', ', array_slice($unknown, 0, 5)).'.';
                }
            }

            if ($flags !== []) {
                $report['unverified_items']++;
            }

            $item['flags'] = $flags;
            $item['verified'] = $flags === [];
            $output['items'][$index] = $item;
        }

        foreach ($output['conflicts'] ?? [] as $index => $conflict) {
            $output['conflicts'][$index]['source_ids'] = array_values(array_filter($conflict['source_ids'] ?? [], fn ($id) => isset($sources[$id])));
        }

        return ['output' => $output, 'report' => $report];
    }

    public function quoteFound(string $quote, string $text): bool
    {
        $needle = $this->normalize($quote);

        return mb_strlen($needle) >= 6 && str_contains($this->normalize($text), $needle);
    }

    protected function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(['“', '”', '‘', '’', '–', '—'], ['"', '"', "'", "'", '-', '-'], $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /** @return array<int, string> */
    protected function unsupportedNumbers(string $text, array $citations, array $sources, array $calcIds, array $calculations): array
    {
        preg_match_all('/\d[\d.,]*\d|\d{2,}/u', $text, $matches);
        $numbers = array_unique($matches[0]);

        if ($numbers === []) {
            return [];
        }

        $haystack = $this->normalize(implode(' ', array_merge(
            array_map(fn ($c) => $sources[$c['source_id']]['text'] ?? '', $citations),
            array_map(fn ($id) => json_encode($calculations[$id] ?? []), $calcIds),
        )));
        preg_match_all('/\d[\d.,]*\d|\d+/u', $haystack, $known);
        $knownDigits = array_flip(array_map(fn ($n) => preg_replace('/[^\d]/', '', $n), $known[0]));

        return array_values(array_filter($numbers, function (string $number) use ($knownDigits) {
            if (preg_match('/^(19|20)\d{2}$/', $number)) {
                return false; // tahun
            }

            return ! isset($knownDigits[preg_replace('/[^\d]/', '', $number)]);
        }));
    }
}
