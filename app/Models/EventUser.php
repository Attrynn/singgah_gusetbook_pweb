<?php

namespace App\Models;

use App\Enums\EventUserRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @deprecated Legacy `event_user` pivot model, retained for backward compatibility only.
 * The canonical model for event ownership is {@see EventOwnerMembership}.
 */
class EventUser extends Pivot
{
    protected $table = 'event_user';

    public $incrementing = true;

    protected $fillable = ['event_id', 'user_id', 'role_label'];

    protected function casts(): array
    {
        return [
            'role_label' => EventUserRole::class,
        ];
    }
}
