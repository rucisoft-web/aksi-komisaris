<?php

namespace App\Services;

use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Mengambil teks dari berkas beserta lokasinya (halaman, slide, lembar, sel).
 *
 * Mengutamakan pustaka PHP murni agar berjalan di cPanel. pdftotext dan
 * tesseract dipakai bila tersedia di server; bila tidak, hasil pindai ditandai
 * "perlu tinjauan" alih-alih dianggap kosong.
 *
 * @phpstan-type Segment array{page_no: ?int, sheet: ?string, cell_range: ?string, text: string}
 */
class TextExtractor
{
    /**
     * @return array{segments: array<int, array>, quality: string, page_count: ?int, note: ?string}
     */
    public function extract(string $absolutePath, string $extension): array
    {
        return match (strtolower($extension)) {
            'pdf' => $this->pdf($absolutePath),
            'docx' => $this->docx($absolutePath),
            'pptx' => $this->pptx($absolutePath),
            'xlsx' => $this->spreadsheet($absolutePath, 'xlsx'),
            'csv' => $this->spreadsheet($absolutePath, 'csv'),
            'txt' => $this->plain($absolutePath),
            'png', 'jpg', 'jpeg' => $this->image($absolutePath),
            default => ['segments' => [], 'quality' => 'tidak_ada_teks', 'page_count' => null, 'note' => 'Format belum didukung untuk ekstraksi teks.'],
        };
    }

    protected function pdf(string $path): array
    {
        $pages = $this->pdfWithPdftotext($path) ?? $this->pdfWithParser($path);

        if ($pages === null) {
            return ['segments' => [], 'quality' => 'tidak_ada_teks', 'page_count' => null, 'note' => 'PDF tidak dapat dibaca.'];
        }

        $segments = [];
        $weakPages = [];
        $min = (int) config('aksi.extraction.min_chars_per_page');

        foreach ($pages as $index => $text) {
            $text = $this->clean($text);

            if (mb_strlen($text) < $min) {
                $ocr = $this->ocrPdfPage($path, $index + 1);

                if ($ocr !== null && mb_strlen($ocr) >= $min) {
                    $text = $ocr;
                } else {
                    $weakPages[] = $index + 1;
                }
            }

            if ($text !== '') {
                $segments[] = ['page_no' => $index + 1, 'sheet' => null, 'cell_range' => null, 'text' => $text];
            }
        }

        $count = count($pages);
        $quality = $weakPages === [] ? 'baik' : (count($weakPages) === $count ? 'tidak_ada_teks' : 'rendah');
        $note = $weakPages === [] ? null : 'Halaman tanpa teks yang memadai (kemungkinan hasil pindai): '.implode(', ', array_slice($weakPages, 0, 30)).(count($weakPages) > 30 ? ', …' : '').'.';

        return ['segments' => $segments, 'quality' => $quality, 'page_count' => $count, 'note' => $note];
    }

    /** @return ?array<int, string> */
    protected function pdfWithPdftotext(string $path): ?array
    {
        $binary = config('aksi.extraction.pdftotext');

        if (! $this->canRun($binary)) {
            return null;
        }

        try {
            $process = new Process([$binary, '-layout', '-enc', 'UTF-8', $path, '-']);
            $process->setTimeout(120)->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $pages = explode("\f", $process->getOutput());

            if (end($pages) === '' || trim((string) end($pages)) === '') {
                array_pop($pages);
            }

            return $pages;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return ?array<int, string> */
    protected function pdfWithParser(string $path): ?array
    {
        try {
            $document = (new PdfParser)->parseFile($path);

            return array_map(fn ($page) => $page->getText(), $document->getPages());
        } catch (Throwable) {
            return null;
        }
    }

    protected function ocrPdfPage(string $path, int $page): ?string
    {
        $tesseract = config('aksi.extraction.tesseract');
        $pdftoppm = dirname((string) config('aksi.extraction.pdftotext')).'/pdftoppm';

        if (! $this->canRun($tesseract) || ! $this->canRun($pdftoppm)) {
            return null;
        }

        $base = tempnam(sys_get_temp_dir(), 'aksi');

        try {
            (new Process([$pdftoppm, '-f', (string) $page, '-l', (string) $page, '-r', '300', '-png', '-singlefile', $path, $base]))->setTimeout(120)->run();

            return is_file($base.'.png') ? $this->tesseract($base.'.png') : null;
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($base);
            @unlink($base.'.png');
        }
    }

    protected function image(string $path): array
    {
        $text = $this->canRun(config('aksi.extraction.tesseract')) ? $this->tesseract($path) : null;

        if ($text === null || mb_strlen($text) < (int) config('aksi.extraction.min_chars_per_page')) {
            return ['segments' => [], 'quality' => 'tidak_ada_teks', 'page_count' => 1, 'note' => 'Gambar hasil pindai perlu OCR. Tesseract tidak tersedia di server atau hasilnya terlalu sedikit.'];
        }

        return ['segments' => [['page_no' => 1, 'sheet' => null, 'cell_range' => null, 'text' => $text]], 'quality' => 'rendah', 'page_count' => 1, 'note' => 'Teks berasal dari OCR; periksa sebelum dipakai sebagai dasar analisis.'];
    }

    protected function tesseract(string $imagePath): ?string
    {
        try {
            $process = new Process([config('aksi.extraction.tesseract'), $imagePath, '-', '-l', 'ind+eng']);
            $process->setTimeout(180)->run();

            return $process->isSuccessful() ? $this->clean($process->getOutput()) : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function docx(string $path): array
    {
        $xml = $this->zipEntry($path, 'word/document.xml');

        if ($xml === null) {
            return ['segments' => [], 'quality' => 'tidak_ada_teks', 'page_count' => null, 'note' => 'DOCX tidak dapat dibaca.'];
        }

        $xml = preg_replace(['~</w:p>~', '~<w:tab/>~', '~<w:br/>~'], ["\n", "\t", "\n"], $xml);
        $text = $this->clean(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));

        return ['segments' => $text === '' ? [] : [['page_no' => null, 'sheet' => null, 'cell_range' => null, 'text' => $text]], 'quality' => $text === '' ? 'tidak_ada_teks' : 'baik', 'page_count' => null, 'note' => null];
    }

    protected function pptx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return ['segments' => [], 'quality' => 'tidak_ada_teks', 'page_count' => null, 'note' => 'PPTX tidak dapat dibaca.'];
        }

        $segments = [];

        for ($slide = 1; ($xml = $zip->getFromName("ppt/slides/slide{$slide}.xml")) !== false; $slide++) {
            $xml = preg_replace('~</a:p>~', "\n", $xml);
            $text = $this->clean(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));

            if ($text !== '') {
                $segments[] = ['page_no' => $slide, 'sheet' => null, 'cell_range' => null, 'text' => $text];
            }
        }

        $zip->close();

        return ['segments' => $segments, 'quality' => $segments === [] ? 'tidak_ada_teks' : 'baik', 'page_count' => $slide - 1, 'note' => null];
    }

    /**
     * Lembar kerja dipotong per 40 baris; setiap baris diberi nomor agar rujukan sel bisa ditelusuri.
     */
    protected function spreadsheet(string $path, string $type): array
    {
        $reader = $type === 'csv' ? new CsvReader : new XlsxReader;
        $segments = [];

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                $name = $type === 'csv' ? 'CSV' : $sheet->getName();
                $buffer = [];
                $startRow = null;
                $maxCol = 1;
                $rowNo = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowNo++;
                    $cells = array_map(fn ($cell) => $this->cellToString($cell->getValue()), $row->getCells());

                    if (implode('', $cells) === '') {
                        continue;
                    }

                    $startRow ??= $rowNo;
                    $maxCol = max($maxCol, count($cells));
                    $buffer[] = 'Baris '.$rowNo.': '.implode(' | ', $cells);

                    if (count($buffer) >= 40) {
                        $segments[] = $this->sheetSegment($name, $startRow, $rowNo, $maxCol, $buffer);
                        $buffer = [];
                        $startRow = null;
                    }
                }

                if ($buffer !== []) {
                    $segments[] = $this->sheetSegment($name, $startRow, $rowNo, $maxCol, $buffer);
                }
            }

            $reader->close();
        } catch (Throwable $e) {
            return ['segments' => [], 'quality' => 'tidak_ada_teks', 'page_count' => null, 'note' => 'Lembar kerja tidak dapat dibaca: '.$e->getMessage()];
        }

        return ['segments' => $segments, 'quality' => $segments === [] ? 'tidak_ada_teks' : 'baik', 'page_count' => null, 'note' => 'Angka dari lembar kerja perlu divalidasi pengguna sebelum dipakai dalam perhitungan.'];
    }

    protected function sheetSegment(string $sheet, int $from, int $to, int $maxCol, array $lines): array
    {
        return [
            'page_no' => null,
            'sheet' => $sheet,
            'cell_range' => 'A'.$from.':'.$this->columnLetter($maxCol).$to,
            'text' => implode("\n", $lines),
        ];
    }

    protected function plain(string $path): array
    {
        $text = $this->clean((string) file_get_contents($path));

        return ['segments' => $text === '' ? [] : [['page_no' => null, 'sheet' => null, 'cell_range' => null, 'text' => $text]], 'quality' => $text === '' ? 'tidak_ada_teks' : 'baik', 'page_count' => null, 'note' => null];
    }

    protected function cellToString(mixed $value): string
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            is_float($value) => rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.'),
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            default => trim((string) $value),
        };
    }

    protected function columnLetter(int $index): string
    {
        $letters = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    protected function zipEntry(string $path, string $entry): ?string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return null;
        }

        $content = $zip->getFromName($entry);
        $zip->close();

        return $content === false ? null : $content;
    }

    protected function clean(string $text): string
    {
        $text = preg_replace("/[ \t]+/u", ' ', str_replace("\r", '', $text)) ?? '';

        return trim(preg_replace("/\n{3,}/u", "\n\n", $text) ?? '');
    }

    protected function canRun(?string $binary): bool
    {
        if (! $binary || ! function_exists('proc_open')) {
            return false;
        }

        if (str_contains($binary, '/')) {
            return is_executable($binary);
        }

        try {
            $process = new Process(['which', $binary]);
            $process->run();

            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }
}
