<?php

namespace App\Http\Controllers\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsCsv
{
    protected function csvResponse(iterable|callable $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $writeRow = function (array $row) use ($out) {
                $sanitized = array_map(function ($val) {
                    if (is_string($val) && strlen($val) > 0) {
                        $first = $val[0];
                        if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
                            return "'".$val;
                        }
                    }

                    return $val;
                }, $row);
                fputcsv($out, $sanitized);
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
}
