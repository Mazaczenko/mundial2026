<?php

namespace Database\Seeders;

use App\Models\WorldMatch;
use App\Services\BetService;
use Illuminate\Database\Seeder;

class FixPenMatchScoresSeeder extends Seeder
{
    // Poprawka dla meczów PEN: API football-data.org pakuje w fullTime sumę 90min+ET+karne,
    // więc FetchFinishedMatchResultsJob przy braku danych karnych wpisał skumulowany wynik
    // zamiast wyniku po 90 min. Wyniki zweryfikowano ręcznie względem FIFA.com.
    //
    // Zasada: score_home/away = wynik po 90 min; score_home/away_et = bramki tylko w dogrywce;
    // score_home/away_pen = wynik rzutów karnych. Dla PEN mecz po 90 min zawsze kończy się
    // remisem, więc result1x2() zwróci 'X' — poprawna odpowiedź dla typerów.
    //
    // Kolejność chronologiczna (ważna dla snapshotów rankingów):
    private const FIXES = [
        537415 => [ // 2026-06-29 R32 | Germany 1–1 Paraguay (PEN 3–4) — Paraguay awansuje
            'score_home'     => 1,
            'score_away'     => 1,
            'score_home_et'  => 0,
            'score_away_et'  => 0,
            'score_home_pen' => 3,
            'score_away_pen' => 4,
            'result_type'    => 'PEN',
        ],
        537418 => [ // 2026-06-30 R32 | Netherlands 1–1 Morocco (PEN 2–3) — Morocco awansuje
            'score_home'     => 1,
            'score_away'     => 1,
            'score_home_et'  => 0,
            'score_away_et'  => 0,
            'score_home_pen' => 2,
            'score_away_pen' => 3,
            'result_type'    => 'PEN',
        ],
        537428 => [ // 2026-07-03 R32 | Australia 1–1 Egypt (PEN 2–4) — Egypt awansuje
            'score_home'     => 1,
            'score_away'     => 1,
            'score_home_et'  => 0,
            'score_away_et'  => 0,
            'score_home_pen' => 2,
            'score_away_pen' => 4,
            'result_type'    => 'PEN',
        ],
        537382 => [ // 2026-07-07 R16 | Switzerland 0–0 Colombia (PEN 4–3) — Switzerland awansuje
            'score_home'     => 0,
            'score_away'     => 0,
            'score_home_et'  => 0,
            'score_away_et'  => 0,
            'score_home_pen' => 4,
            'score_away_pen' => 3,
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
