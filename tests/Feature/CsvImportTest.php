<?php

namespace Tests\Feature;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Models\Event;
use App\Models\User;
use App\Services\GuestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    protected function import(Event $event, string $csv): array
    {
        $path = tempnam(sys_get_temp_dir(), 'vowspace-csv-');
        file_put_contents($path, $csv);

        try {
            return app(GuestService::class)->importCsv($event, $path, User::factory()->superadmin()->create());
        } finally {
            @unlink($path);
        }
    }

    public function test_utf8_bom_and_valid_rows_are_imported(): void
    {
        $event = $this->makeEvent();

        $result = $this->import($event, "\xEF\xBB\xBFname,email,category\nAyu,ayu@example.com,Family\nBima,bima@example.com,Friend\n");

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(2, $event->guests()->count());
    }

    public function test_duplicate_headers_are_rejected(): void
    {
        $event = $this->makeEvent();

        $result = $this->import($event, "name,name\nAyu,Ayu\n");

        $this->assertSame(0, $result['imported']);
        $this->assertStringContainsString('duplikat', strtolower(implode(' ', $result['errors'])));
    }

    public function test_unknown_headers_are_rejected_instead_of_silently_dropped(): void
    {
        $event = $this->makeEvent();

        $result = $this->import($event, "name,foo\nAyu,bar\n");

        $this->assertSame(0, $result['imported']);
        $this->assertStringContainsString('tidak dikenal', strtolower(implode(' ', $result['errors'])));
    }

    public function test_rows_with_extra_columns_are_rejected(): void
    {
        $event = $this->makeEvent();

        $result = $this->import($event, "name\nAyu,extra\n");

        $this->assertSame(0, $result['imported']);
        $this->assertStringContainsString('melebihi header', strtolower(implode(' ', $result['errors'])));
    }

    public function test_invalid_numeric_values_are_rejected(): void
    {
        $event = $this->makeEvent();

        $result = $this->import($event, "name,max_attendees\nAyu,abc\n");

        $this->assertSame(0, $result['imported']);
        $this->assertStringContainsString('bilangan bulat', strtolower(implode(' ', $result['errors'])));
    }

    public function test_import_over_the_configured_limit_is_rejected_without_writing_rows(): void
    {
        config(['vowspace.guest_import_limit' => 2]);
        $event = $this->makeEvent();

        $result = $this->import($event, "name\nA\nB\nC\n");

        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $event->guests()->count());
        $this->assertStringContainsString('melebihi batas', strtolower(implode(' ', $result['errors'])));
    }

    public function test_formula_injection_payloads_are_neutralised(): void
    {
        $sanitizer = new class
        {
            use ExportsCsv;

            public function clean(mixed $value): mixed
            {
                return $this->sanitizeCsvValue($value);
            }
        };

        $this->assertSame("'=cmd", $sanitizer->clean('=cmd'));
        $this->assertSame("'+cmd", $sanitizer->clean('+cmd'));
        $this->assertSame("'-cmd", $sanitizer->clean('-cmd'));
        $this->assertSame("'@cmd", $sanitizer->clean('@cmd'));
        $this->assertSame("' =cmd", $sanitizer->clean(' =cmd'));
        $this->assertSame("'\t=cmd", $sanitizer->clean("\t=cmd"));
        $this->assertSame("'＠cmd", $sanitizer->clean('＠cmd'));
        $this->assertSame('safe value', $sanitizer->clean('safe value'));
    }
}
