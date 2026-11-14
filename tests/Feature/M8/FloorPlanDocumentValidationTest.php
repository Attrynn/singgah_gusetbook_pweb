<?php

namespace Tests\Feature\M8;

use App\Services\FloorPlanDocumentValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\M8\Concerns\BuildsSpatialFixtures;
use Tests\TestCase;

class FloorPlanDocumentValidationTest extends TestCase
{
    use BuildsSpatialFixtures, RefreshDatabase;

    private function validator(): FloorPlanDocumentValidator
    {
        return app(FloorPlanDocumentValidator::class);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function assertRejected(array $document): void
    {
        try {
            $this->validator()->validate($document);
            $this->fail('Document was accepted but should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }
    }

    public function test_a_valid_document_is_normalized(): void
    {
        $document = $this->document([
            $this->tableObject('table_aaaaaaaa', [
                'label' => '<b>Meja Utama</b>',
                'rotation' => -90,
            ]),
            $this->chairObject('chair_aaaaaaaa', 'table_aaaaaaaa', 0),
        ], [
            [
                'id' => 'zone_aaaaaaaa',
                'type' => 'restricted',
                'name' => 'Area Terbatas',
                'geometry' => ['kind' => 'polygon', 'points' => [[0, 0], [100, 0], [100, 100]]],
            ],
        ]);

        $normalized = $this->validator()->validate($document);

        $this->assertSame(1, $normalized['schema_version']);
        $this->assertSame('cm', $normalized['canvas']['unit']);
        $this->assertSame('Meja Utama', $normalized['objects'][0]['label']);
        $this->assertSame(270.0, $normalized['objects'][0]['rotation']);
        $this->assertSame('restricted', $normalized['zones'][0]['type']);
        $this->assertCount(3, $normalized['zones'][0]['geometry']['points']);
    }

    public function test_unknown_object_type_is_rejected(): void
    {
        $this->assertRejected($this->document([
            $this->tableObject('table_aaaaaaaa', ['type' => 'portal']),
        ]));
    }

    public function test_unknown_zone_type_is_rejected(): void
    {
        $this->assertRejected($this->document([], [
            [
                'id' => 'zone_aaaaaaaa',
                'type' => 'nowhere',
                'name' => 'Zona',
                'geometry' => ['kind' => 'rect', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10],
            ],
        ]));
    }

    public function test_duplicate_identifiers_are_rejected(): void
    {
        $this->assertRejected($this->document([
            $this->tableObject('duplicate_id_1'),
            $this->tableObject('duplicate_id_1'),
        ]));
    }

    public function test_invalid_identifier_format_is_rejected(): void
    {
        $this->assertRejected($this->document([
            $this->tableObject('bad id!'),
        ]));
    }

    public function test_chair_must_reference_an_existing_table(): void
    {
        $this->assertRejected($this->document([
            $this->chairObject('chair_aaaaaaaa', 'table_missing0'),
        ]));
    }

    public function test_coordinates_outside_the_allowed_range_are_rejected(): void
    {
        $this->assertRejected($this->document([
            $this->tableObject('table_aaaaaaaa', ['x' => 200000]),
        ]));
    }

    public function test_table_capacity_outside_the_range_is_rejected(): void
    {
        $this->assertRejected($this->document([
            $this->tableObject('table_aaaaaaaa', ['properties' => ['capacity' => 201]]),
        ]));
    }

    public function test_property_keys_must_be_snake_case(): void
    {
        $this->assertRejected($this->document([
            $this->tableObject('table_aaaaaaaa', ['properties' => ['Bad Key' => 1]]),
        ]));
    }

    public function test_polygon_zone_requires_at_least_three_points(): void
    {
        $this->assertRejected($this->document([], [
            [
                'id' => 'zone_aaaaaaaa',
                'type' => 'vip',
                'name' => 'Zona',
                'geometry' => ['kind' => 'polygon', 'points' => [[0, 0], [10, 10]]],
            ],
        ]));
    }

    public function test_unsupported_schema_version_is_rejected(): void
    {
        $document = $this->document();
        $document['schema_version'] = 99;

        $this->assertRejected($document);
    }

    public function test_payload_larger_than_two_megabytes_is_rejected(): void
    {
        $document = $this->document([
            $this->tableObject('table_aaaaaaaa'),
        ]);
        $document['padding'] = str_repeat('x', FloorPlanDocumentValidator::MAX_PAYLOAD_BYTES + 10);

        $this->assertRejected($document);
    }

    public function test_more_than_two_thousand_objects_is_rejected(): void
    {
        $objects = [];

        for ($index = 0; $index <= FloorPlanDocumentValidator::MAX_OBJECTS; $index++) {
            $objects[] = $this->tableObject('table_'.str_pad((string) $index, 8, '0', STR_PAD_LEFT));
        }

        $this->assertRejected($this->document($objects));
    }

    public function test_more_than_two_hundred_zones_is_rejected(): void
    {
        $zones = [];

        for ($index = 0; $index <= FloorPlanDocumentValidator::MAX_ZONES; $index++) {
            $zones[] = [
                'id' => 'zone_'.str_pad((string) $index, 8, '0', STR_PAD_LEFT),
                'type' => 'vip',
                'name' => 'Zona '.$index,
                'geometry' => ['kind' => 'rect', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10],
            ];
        }

        $this->assertRejected($this->document([], $zones));
    }

    public function test_missing_objects_array_is_rejected(): void
    {
        $this->assertRejected([
            'schema_version' => 1,
            'canvas' => [],
            'zones' => [],
        ]);
    }

    public function test_canvas_dimensions_below_the_minimum_are_rejected(): void
    {
        $this->assertRejected($this->document([], [], ['width' => 10]));
    }

    public function test_canvas_unit_is_forced_to_centimeters(): void
    {
        $normalized = $this->validator()->validate($this->document([], [], ['unit' => 'cm']));

        $this->assertSame('cm', $normalized['canvas']['unit']);
    }

    public function test_invalid_canvas_unit_is_rejected(): void
    {
        $this->assertRejected($this->document([], [], ['unit' => 'px']));
    }
}
