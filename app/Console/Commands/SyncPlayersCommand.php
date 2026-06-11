<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Services\FootballApiService;
use Illuminate\Console\Command;

class SyncPlayersCommand extends Command
{
    protected $signature = 'mundial:sync-players';

    protected $description = 'Synchronizuje składy drużyn z football-data.org';

    public function handle(FootballApiService $api): int
    {
        $teams = $api->getTeams();

        if (empty($teams)) {
            $this->error('Brak danych z API. Sprawdź klucz API i limit zapytań.');

            return self::FAILURE;
        }

        $synced = 0;

        foreach ($teams as $team) {
            foreach ($team['squad'] ?? [] as $player) {
                Player::updateOrCreate(
                    ['api_player_id' => $player['id']],
                    [
                        'name' => $player['name'],
                        'position' => $player['position'] ?? null,
                        'team_name' => $team['name'],
                        'api_team_id' => $team['id'],
                    ]
                );
                $synced++;
            }
        }

        $this->info("Zsynchronizowano {$synced} graczy.");

        return self::SUCCESS;
    }
}
