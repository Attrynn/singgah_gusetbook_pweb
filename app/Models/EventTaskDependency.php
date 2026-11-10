<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTaskDependency extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_task_id',
        'depends_on_task_id',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(EventTask::class, 'event_task_id');
    }

    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(EventTask::class, 'depends_on_task_id');
    }
}
