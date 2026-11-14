<?php

namespace App\Services;

use App\Enums\FloorPlanObjectType;
use App\Enums\FloorPlanZoneType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates and normalizes the floor plan canvas document.
 *
 * The browser is never trusted: every object, coordinate, identifier and
 * property bag is re-checked here before a version is persisted. Objects are
 * addressed by stable string ids inside the version document.
 */
class FloorPlanDocumentValidator
{
    public const SCHEMA_VERSION = 1;

    public const MAX_PAYLOAD_BYTES = 2_097_152;

    public const MAX_OBJECTS = 2000;

    public const MAX_ZONES = 200;

    public const MAX_COORDINATE = 100_000;

    private const ID_PATTERN = '/^[A-Za-z0-9_-]{8,64}$/';

    /**
     * @return array<string, mixed> normalized document
     *
     * @throws ValidationException
     */
    public function validate(array $document): array
    {
        $encoded = json_encode($document, JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            $this->fail('Dokumen layout tidak dapat diserialisasi.');
        }

        if (strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
            $this->fail('Ukuran dokumen layout melebihi batas 2 MB.');
        }

        $validator = Validator::make($document, [
            'schema_version' => ['nullable', 'integer', 'min:1', 'max:'.self::SCHEMA_VERSION],
            'canvas' => ['nullable', 'array'],
            'objects' => ['present', 'array', 'max:'.self::MAX_OBJECTS],
            'zones' => ['nullable', 'array', 'max:'.self::MAX_ZONES],
        ]);

        if ($validator->fails()) {
            $this->fail($validator->errors()->all());
        }

        $schemaVersion = (int) ($document['schema_version'] ?? self::SCHEMA_VERSION);

        if ($schemaVersion !== self::SCHEMA_VERSION) {
            $this->fail('Versi schema dokumen layout tidak didukung.');
        }

        $canvas = $this->normalizeCanvas($document['canvas'] ?? []);
        $objects = $this->normalizeObjects($document['objects'] ?? []);
        $zones = $this->normalizeZones($document['zones'] ?? []);

        $this->assertUniqueIds($objects, $zones);
        $this->assertChairTableLinks($objects);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'canvas' => $canvas,
            'objects' => $objects,
            'zones' => $zones,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $objects
     * @param  array<int, array<string, mixed>>  $zones
     */
    private function assertUniqueIds(array $objects, array $zones): void
    {
        $ids = [];

        foreach ([...$objects, ...$zones] as $item) {
            $id = $item['id'];

            if (isset($ids[$id])) {
                $this->fail("Identifier objek duplikat: {$id}.");
            }

            $ids[$id] = true;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $objects
     */
    private function assertChairTableLinks(array $objects): void
    {
        $tables = [];

        foreach ($objects as $object) {
            if (FloorPlanObjectType::from($object['type'])->isTable()) {
                $tables[$object['id']] = true;
            }
        }

        foreach ($objects as $object) {
            $parentId = $object['properties']['table_id'] ?? null;

            if ($parentId === null) {
                continue;
            }

            if (! is_string($parentId) || ! isset($tables[$parentId])) {
                $this->fail("Kursi {$object['id']} merujuk meja yang tidak ada dalam layout.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $canvas
     * @return array<string, mixed>
     */
    private function normalizeCanvas(array $canvas): array
    {
        $validator = Validator::make($canvas, [
            'width' => ['nullable', 'numeric', 'min:100', 'max:'.self::MAX_COORDINATE],
            'height' => ['nullable', 'numeric', 'min:100', 'max:'.self::MAX_COORDINATE],
            'unit' => ['nullable', 'string', 'in:cm'],
            'grid_size' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'grid_visible' => ['nullable', 'boolean'],
            'snap_enabled' => ['nullable', 'boolean'],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if ($validator->fails()) {
            $this->fail($validator->errors()->all());
        }

        return [
            'width' => (float) ($canvas['width'] ?? 2000),
            'height' => (float) ($canvas['height'] ?? 1500),
            'unit' => 'cm',
            'grid_size' => (float) ($canvas['grid_size'] ?? 50),
            'grid_visible' => (bool) ($canvas['grid_visible'] ?? true),
            'snap_enabled' => (bool) ($canvas['snap_enabled'] ?? true),
            'background_color' => $canvas['background_color'] ?? '#ffffff',
        ];
    }

    /**
     * @param  array<int, mixed>  $objects
     * @return array<int, array<string, mixed>>
     */
    private function normalizeObjects(array $objects): array
    {
        $normalized = [];

        foreach ($objects as $index => $object) {
            if (! is_array($object)) {
                $this->fail("Objek pada indeks {$index} tidak valid.");
            }

            $validator = Validator::make($object, [
                'id' => ['required', 'string', 'regex:'.self::ID_PATTERN],
                'type' => ['required', 'string'],
                'x' => ['required', 'numeric', 'min:-'.self::MAX_COORDINATE, 'max:'.self::MAX_COORDINATE],
                'y' => ['required', 'numeric', 'min:-'.self::MAX_COORDINATE, 'max:'.self::MAX_COORDINATE],
                'width' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_COORDINATE],
                'height' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_COORDINATE],
                'rotation' => ['nullable', 'numeric', 'min:-360', 'max:360'],
                'z_index' => ['nullable', 'integer', 'min:-10000', 'max:10000'],
                'visible' => ['nullable', 'boolean'],
                'locked' => ['nullable', 'boolean'],
                'label' => ['nullable', 'string', 'max:120'],
                'group_id' => ['nullable', 'string', 'regex:'.self::ID_PATTERN],
                'parent_id' => ['nullable', 'string', 'regex:'.self::ID_PATTERN],
                'properties' => ['nullable', 'array'],
            ]);

            if ($validator->fails()) {
                $this->fail($validator->errors()->all());
            }

            $type = FloorPlanObjectType::tryFrom((string) $object['type']);

            if ($type === null) {
                $this->fail("Tipe objek tidak terdaftar: {$object['type']}.");
            }

            $properties = $this->normalizeProperties($object['properties'] ?? []);

            if ($type->isTable()) {
                $properties = $this->normalizeTableProperties($properties);
            }

            if ($type->isChair()) {
                $properties = $this->normalizeChairProperties($properties);
            }

            $normalized[] = [
                'id' => (string) $object['id'],
                'type' => $type->value,
                'x' => $this->round((float) $object['x']),
                'y' => $this->round((float) $object['y']),
                'width' => $this->round((float) $object['width']),
                'height' => $this->round((float) $object['height']),
                'rotation' => $this->normalizeRotation((float) ($object['rotation'] ?? 0)),
                'z_index' => (int) ($object['z_index'] ?? 0),
                'visible' => (bool) ($object['visible'] ?? true),
                'locked' => (bool) ($object['locked'] ?? false),
                'label' => $this->plainText($object['label'] ?? null, 120),
                'group_id' => $object['group_id'] ?? null,
                'parent_id' => $object['parent_id'] ?? null,
                'properties' => $properties,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $zones
     * @return array<int, array<string, mixed>>
     */
    private function normalizeZones(array $zones): array
    {
        $normalized = [];

        foreach ($zones as $index => $zone) {
            if (! is_array($zone)) {
                $this->fail("Zona pada indeks {$index} tidak valid.");
            }

            $validator = Validator::make($zone, [
                'id' => ['required', 'string', 'regex:'.self::ID_PATTERN],
                'type' => ['required', 'string'],
                'name' => ['required', 'string', 'max:120'],
                'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'description' => ['nullable', 'string', 'max:1000'],
                'visible' => ['nullable', 'boolean'],
                'capacity' => ['nullable', 'integer', 'min:0', 'max:100000'],
                'geometry' => ['required', 'array'],
            ]);

            if ($validator->fails()) {
                $this->fail($validator->errors()->all());
            }

            $type = FloorPlanZoneType::tryFrom((string) $zone['type']);

            if ($type === null) {
                $this->fail("Tipe zona tidak terdaftar: {$zone['type']}.");
            }

            $normalized[] = [
                'id' => (string) $zone['id'],
                'type' => $type->value,
                'name' => $this->plainText($zone['name'], 120),
                'color' => $zone['color'] ?? null,
                'description' => $this->plainText($zone['description'] ?? null, 1000),
                'visible' => (bool) ($zone['visible'] ?? true),
                'capacity' => isset($zone['capacity']) ? (int) $zone['capacity'] : null,
                'geometry' => $this->normalizeGeometry($zone['geometry']),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $geometry
     * @return array<string, mixed>
     */
    private function normalizeGeometry(array $geometry): array
    {
        $kind = $geometry['kind'] ?? null;

        if ($kind === 'rect') {
            $validator = Validator::make($geometry, [
                'x' => ['required', 'numeric', 'min:-'.self::MAX_COORDINATE, 'max:'.self::MAX_COORDINATE],
                'y' => ['required', 'numeric', 'min:-'.self::MAX_COORDINATE, 'max:'.self::MAX_COORDINATE],
                'width' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_COORDINATE],
                'height' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_COORDINATE],
            ]);

            if ($validator->fails()) {
                $this->fail($validator->errors()->all());
            }

            return [
                'kind' => 'rect',
                'x' => $this->round((float) $geometry['x']),
                'y' => $this->round((float) $geometry['y']),
                'width' => $this->round((float) $geometry['width']),
                'height' => $this->round((float) $geometry['height']),
            ];
        }

        if ($kind === 'polygon') {
            $points = $geometry['points'] ?? null;

            if (! is_array($points) || count($points) < 3 || count($points) > 200) {
                $this->fail('Poligon zona memerlukan 3 sampai 200 titik.');
            }

            $normalizedPoints = [];

            foreach ($points as $point) {
                if (! is_array($point) || count($point) !== 2) {
                    $this->fail('Titik poligon tidak valid.');
                }

                [$x, $y] = array_values($point);

                if (! is_numeric($x) || ! is_numeric($y)) {
                    $this->fail('Koordinat titik poligon harus berupa angka.');
                }

                $normalizedPoints[] = [
                    $this->round((float) $x),
                    $this->round((float) $y),
                ];
            }

            return ['kind' => 'polygon', 'points' => $normalizedPoints];
        }

        $this->fail('Jenis geometri zona tidak didukung.');
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function normalizeProperties(array $properties): array
    {
        if (count($properties) > 30) {
            $this->fail('Properti objek melebihi batas 30 kunci.');
        }

        $normalized = [];

        foreach ($properties as $key => $value) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key)) {
                $this->fail('Nama properti objek tidak valid.');
            }

            $normalized[$key] = $this->normalizePropertyValue($value, 1);
        }

        return $normalized;
    }

    private function normalizePropertyValue(mixed $value, int $depth): mixed
    {
        if ($depth > 3) {
            $this->fail('Struktur properti objek terlalu dalam.');
        }

        if (is_array($value)) {
            if (count($value) > 50) {
                $this->fail('Daftar properti objek terlalu panjang.');
            }

            return array_map(fn (mixed $item): mixed => $this->normalizePropertyValue($item, $depth + 1), $value);
        }

        if (is_string($value)) {
            if (strlen($value) > 500) {
                $this->fail('Nilai properti objek terlalu panjang.');
            }

            return $this->plainText($value, 500);
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        $this->fail('Tipe nilai properti objek tidak didukung.');
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function normalizeTableProperties(array $properties): array
    {
        $capacity = $properties['capacity'] ?? 0;

        if (! is_int($capacity) || $capacity < 0 || $capacity > 200) {
            $this->fail('Kapasitas meja harus antara 0 dan 200.');
        }

        $properties['capacity'] = $capacity;
        $properties['table_number'] = $this->plainText($properties['table_number'] ?? null, 20);

        return $properties;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function normalizeChairProperties(array $properties): array
    {
        if (isset($properties['table_id']) && ! is_string($properties['table_id'])) {
            $this->fail('Referensi meja pada kursi tidak valid.');
        }

        if (isset($properties['seat_index'])) {
            if (! is_int($properties['seat_index']) || $properties['seat_index'] < 0 || $properties['seat_index'] > 500) {
                $this->fail('Indeks kursi tidak valid.');
            }
        }

        return $properties;
    }

    private function normalizeRotation(float $rotation): float
    {
        $rotation = fmod($rotation, 360.0);

        if ($rotation < 0) {
            $rotation += 360.0;
        }

        return $this->round($rotation);
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }

    private function plainText(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim(strip_tags($value));

        if ($clean === '') {
            return null;
        }

        return mb_substr($clean, 0, $max);
    }

    /**
     * @param  string|array<int, string>  $messages
     *
     * @throws ValidationException
     */
    private function fail(string|array $messages): never
    {
        throw ValidationException::withMessages([
            'document' => is_array($messages) ? $messages : [$messages],
        ]);
    }
}
