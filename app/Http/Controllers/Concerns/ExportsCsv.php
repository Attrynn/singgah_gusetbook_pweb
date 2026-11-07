<?php

namespace App\Http\Controllers\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsCsv
{
    /**
     * Stream CSV rows without loading the dataset into memory.
     *
     * @param  iterable<int,array<int,mixed>>|callable(callable(array<int,mixed>):void):void  $rows
     */
    protected function csvResponse(iterable|callable $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            $writeRow = function (array $row) use ($out): void {
                fputcsv($out, array_map(fn ($value) => $this->sanitizeCsvValue($value), $row));
            };

            if (is_callable($rows)) {
                $rows($writeRow);
            } else {
                foreach ($rows as $row) {
                    $writeRow($row);
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Neutralise spreadsheet formula injection, including values that hide a
     * dangerous character behind leading whitespace or use fullwidth variants.
     */
    protected function sanitizeCsvValue(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        if (! is_string($value)) {
            return $value;
        }

        if ($value === '') {
            return $value;
        }

        // Remove control characters that are not meaningful in CSV text.
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        if ($cleaned === null) {
            $cleaned = $value;
        }

        $trimmed = ltrim($cleaned, " \t\r\n");

        if ($trimmed !== '' && preg_match('/^[=+\-@\t\r\n]/u', $trimmed) === 1) {
            return "'".$cleaned;
        }

        // Fullwidth / unicode variants that some spreadsheet tools normalise.
        if ($trimmed !== '' && preg_match('/^[＝＋－＠]/u', $trimmed) === 1) {
            return "'".$cleaned;
        }

        return $cleaned;
    }
}
