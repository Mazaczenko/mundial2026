<?php

namespace App\Jobs;

use App\Services\FootballApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncStandingsJob implements ShouldQueue
{
    use Queueable;

    public function handle(FootballApiService $footballApi): void
    {
        // football-data.org returns array of standing groups
        // each: { stage, type, group, table: [{position, team, playedGames, won, draw, lost, points, goalsFor, goalsAgainst}] }
        $standingsData = $footballApi->getStandings();

        if (empty($standingsData)) {
            return;
        }

        $rows = [];
        $now = Carbon::now()->toDateTimeString();

        foreach ($standingsData as $groupData) {
            if (($groupData['type'] ?? '') !== 'TOTAL') {
                continue;
            }

            // "GROUP_A" → "A"
            $groupRaw = $groupData['group'] ?? '';
            preg_match('/GROUP_([A-L])/i', $groupRaw, $m);
            $groupName = $m[1] ?? $groupRaw;

            foreach ($groupData['table'] as $entry) {
                $rows[] = [
                    'group_name'    => strtoupper($groupName),
                    'api_team_id'   => $entry['team']['id'],
                    'team_name'     => $entry['team']['name'],
                    'team_flag'     => $entry['team']['crest'] ?? null,
                    'position'      => $entry['position'],
                    'played'        => $entry['playedGames'] ?? 0,
                    'won'           => $entry['won'] ?? 0,
                    'drawn'         => $entry['draw'] ?? 0,
                    'lost'          => $entry['lost'] ?? 0,
                    'goals_for'     => $entry['goalsFor'] ?? 0,
                    'goals_against' => $entry['goalsAgainst'] ?? 0,
                    'points'        => $entry['points'] ?? 0,
                    'synced_at'     => $now,
                ];
            }
        }

        DB::table('group_standings')->truncate();

        if (! empty($rows)) {
            DB::table('group_standings')->insert($rows);
        }
    }
}
