<?php

namespace App\Models;

use App\Enums\PlaybookStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Playbook extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'event_type',
        'days',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'integer',
            'status' => PlaybookStatus::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PlaybookVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(PlaybookVersion::class)->latestOfMany('version');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePlatform(Builder $query): Builder
    {
        return $query->whereNull('organization_id');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function isPlatform(): bool
    {
        return $this->organization_id === null;
    }
}
