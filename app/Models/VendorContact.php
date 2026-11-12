<?php

namespace App\Models;

use App\Enums\VendorContactPurpose;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'name',
        'position',
        'email',
        'phone',
        'purpose',
        'is_primary',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => VendorContactPurpose::class,
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(EventVendorDeliverable::class, 'vendor_contact_id');
    }
}
