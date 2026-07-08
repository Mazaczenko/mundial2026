<?php

namespace Database\Seeders;

use App\Models\WorldMatch;
use App\Services\BetService;
use Illuminate\Database\Seeder;

class FixPenMatchScoresSeeder extends Seeder
{
    // Poprawka dla meczów PEN gdzie score_home/away miały błędnie zapisany wynik karny
    // zamiast wyniku po 90 min. Identyfikacja po api_fixture_id (stabilne między środowiskami).
    private const FIXES = [
        537382 => [ // Switzerland vs Colombia — 0:0 po 90 min, karne 4:3
            'score_home'     => 0,
            'score_away'     => 0,
            'score_home_et'  => 0,
            'score_away_et'  => 0,
            'score_home_pen' => 4,
            'score_away_pen' => 3,
            'result_type'    => 'PEN',
        ],
        537415 => [ // Germany vs Paraguay — 1:1 po 90 min, karne 3:4
            'score_home'     => 1,
            'score_away'     => 1,
            'score_home_et'  => 0,
            'score_away_et'  => 0,
            'score_home_pen' => 3,
            'score_away_pen' => 4,
            'result_type'    => 'PEN',
        ],
        537418 => [ // Netherlands vs Morocco — 1:1 po 90 min, karne 2:3
            'score_home'     => 1,
            'score_away'     => 1,
            'score_home_et'  => 0,
            'score_away_et'  => 0,
            'score_home_pen' => 2,
            'score_away_pen' => 3,
            'result_type'    => 'PEN',
        ],
        537428 => [ // Australia vs Egypt — 1:1 po 90 min, karne 2:4
            'score_home'     => 1,
            'score_away'     => 1,
            'score_home_et'  => 0,
            'score_away_et'  => 0,
            'score_home_pen' => 2,
            'score_away_pen' => 4,
            'result_type'    => 'PEN',
        ],
    ];

    public function run(BetService $betService): void
    {
        foreach (self::FIXES as $fixtureId => $data) {
            $match = WorldMatch::where('api_fixture_id', $fixtureId)->first();

            if ($match === null) {
                $this->command->warn("Nie znaleziono meczu api_fixture_id={$fixtureId}, pomijam.");
                continue;
            }

            $match->update($data);
            $match->refresh()->load('bets');
            $betService->resolveBets($match);

            $this->command->info(
                "Naprawiono #{$match->id} {$match->home_team} vs {$match->away_team}: "
                . "{$match->score_home}:{$match->score_away} (PEN {$match->score_home_pen}:{$match->score_away_pen}), "
                . "typy przeliczone."
            );
        }
    }
}
