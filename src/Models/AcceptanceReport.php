<?php

namespace Homemove\AbTesting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcceptanceReport extends Model
{
    protected $table = 'ab_acceptance_reports';

    protected $fillable = [
        'experiment_id',
        'accepted_variant',
        'status',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }
}
