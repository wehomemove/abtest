<?php

namespace Homemove\AbTesting\Models;

use Illuminate\Database\Eloquent\Model;

class AbEventCount extends Model
{
    protected $table = 'ab_event_counts';
    
    protected $fillable = [
        'experiment_id',
        'user_id',
        'event_name',
        'variant',
        'count',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
        'count' => 'integer',
    ];

    public function experiment()
    {
        return $this->belongsTo(Experiment::class);
    }

    /**
     * Increment the count for this user/event combination
     */
    public static function incrementCount($experimentId, $userId, $eventName, $variant, $properties = [])
    {
        return self::updateOrCreate(
            [
                'experiment_id' => $experimentId,
                'user_id' => $userId,
                'event_name' => $eventName,
                'variant' => $variant,
            ],
            [
                'properties' => $properties,
            ]
        )->increment('count');
    }
}