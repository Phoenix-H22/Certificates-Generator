<?php

namespace App\Certificates\Pipeline;

use App\Certificates\Rendering\DataMerger;
use Illuminate\Support\Collection;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Rap2hpoutre\FastExcel\FastExcel;
use RuntimeException;

/** Reads participant spreadsheets (xlsx) into header-keyed rows. */
final class SheetReader
{
    /**
     * Header row as written in the file (trimmed, original case), so the
     * admin can map columns by what they see in Excel.
     *
     * @return list<string>
     */
    public function headers(string $absolutePath): array
    {
        $this->assertReadable($absolutePath);

        $reader = new XlsxReader;
        $reader->open($absolutePath);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $headers = [];

                    foreach ($row->toArray() as $cell) {
                        $headers[] = trim((string) $cell);
                    }

                    // Drop trailing empty header cells Excel likes to keep.
                    while ($headers !== [] && end($headers) === '') {
                        array_pop($headers);
                    }

                    return $headers;
                }

                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return [];
    }

    /**
     * All data rows keyed by normalised header (see DataMerger::normaliseHeader).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(string $absolutePath, ?int $maxRows = null): Collection
    {
        $this->assertReadable($absolutePath);

        $rows = (new FastExcel)->import($absolutePath);

        $maxRows ??= (int) config('certificates.max_rows', 5000);

        if ($rows->count() > $maxRows) {
            throw new RuntimeException("The sheet has {$rows->count()} rows; the limit is {$maxRows}.");
        }

        return $rows
            ->map(fn (array $row) => DataMerger::normaliseHeaders($row))
            ->filter(fn (array $row) => collect($row)->contains(fn ($v) => $v !== null && trim((string) $v) !== ''))
            ->values();
    }

    private function assertReadable(string $absolutePath): void
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException("Spreadsheet not found or unreadable: {$absolutePath}");
        }
    }
}
