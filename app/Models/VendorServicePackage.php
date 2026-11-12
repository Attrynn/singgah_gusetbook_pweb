<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorServicePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_service_id',
        'name',
        'description',
        'price_amount',
        'currency',
        'inclusions',
        'exclusions',
        'capacity',
        'conditions',
        'is_active',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'decimal:2',
            'inclusions' => 'array',
            'exclusions' => 'array',
            'is_active' => 'boolean',
            'capacity' => 'integer',
            'version' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(VendorService::class, 'vendor_service_id');
    }
}
