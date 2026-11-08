<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaybookVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'playbook_id',
        'version',
        'config',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'config' => 'array',
        ];
    }

    public function playbook(): BelongsTo
    {
        return $this->belongsTo(Playbook::class);
    }
}
