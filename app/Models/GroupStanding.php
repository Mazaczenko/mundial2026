<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GroupStanding extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'group_name',
        'api_team_id',
        'team_name',
        'team_flag',
        'position',
        'played',
        'won',
        'drawn',
        'lost',
        'goals_for',
        'goals_against',
        'points',
        'synced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function scopeForGroup(Builder $query, string $group): Builder
    {
        return $query->where('group_name', $group)->orderBy('position');
    }
}
