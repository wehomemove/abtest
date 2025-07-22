<?php

namespace Homemove\AbTesting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    protected $table = 'ab_events';

    protected $fillable = [
        'experiment_id',
        'user_id',
        'variant',
        'event_name',
        'properties',
        'event_time',
    ];

    protected $casts = [
        'properties' => 'array',
        'event_time' => 'datetime',
    ];

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(UserAssignment::class, ['experiment_id', 'user_id'], ['experiment_id', 'user_id']);
    }
}