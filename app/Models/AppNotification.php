<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'notifications';

    /** @var list<string> */
    protected $fillable = [
        'participant_id',
        'type',
        'title',
        'body',
        'url',
        'meta',
        'read_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'meta' => 'array',
        'read_at' => 'datetime',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
