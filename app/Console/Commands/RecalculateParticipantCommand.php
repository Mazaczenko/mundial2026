<?php

namespace App\Console\Commands;

use App\Models\Participant;
use App\Models\WorldMatch;
use Illuminate\Console\Command;

class RecalculateParticipantCommand extends Command
{
    protected $signature = 'mundial:recalculate-participant {participant : ID lub nazwa gracza}';

    protected $description = 'Przelicz is_correct dla wszystkich typów danego gracza';

    public function handle(): int
    {
        $input = $this->argument('participant');

        $participant = is_numeric($input)
            ? Participant::find((int) $input)
            : Participant::where('name', $input)->first();

        if (! $participant) {
            $this->error("Nie znaleziono gracza: {$input}");

            return self::FAILURE;
        }

        $this->info("Gracz: {$participant->name} (ID: {$participant->id})");

        $matches = WorldMatch::finished()
            ->whereNotNull('score_home')
            ->whereNotNull('score_away')
            ->orderBy('kickoff_at')
            ->get();

        if ($matches->isEmpty()) {
            $this->warn('Brak zakończonych meczów.');

            return self::SUCCESS;
        }

        $bets = $participant->bets()
            ->whereIn('match_id', $matches->pluck('id'))
            ->get()
            ->keyBy('match_id');

        $correct = 0;
        $updated = 0;

        foreach ($matches as $match) {
            $bet = $bets->get($match->id);

            if (! $bet) {
                continue;
            }

            $result = $match->result1x2();
            $correct1x2 = ($bet->prediction_1x2 === $result);
            $correctScore = $match->isKnockout()
                && $bet->predicted_home !== null
                && $bet->predicted_away !== null
                && (int) $bet->predicted_home === (int) $match->score_home
                && (int) $bet->predicted_away === (int) $match->score_away;

            $isCorrect = $correct1x2 || $correctScore;

            if ($bet->is_correct !== $isCorrect) {
                $bet->is_correct = $isCorrect;
                $bet->save();
                $updated++;
            }

            if ($isCorrect) {
                $correct++;
            }

            $pts = match (true) {
                $correctScore && $correct1x2 => 2,
                $correct1x2 || $correctScore => 1,
                default                       => 0,
            };

            $scoreStr = "{$match->score_home}:{$match->score_away}";
            $betStr   = $bet->predicted_home !== null
                ? "{$bet->prediction_1x2} ({$bet->predicted_home}:{$bet->predicted_away})"
                : $bet->prediction_1x2;
            $mark = $pts > 0 ? "✓ +{$pts}p" : '✗';

            $this->line("  {$mark} {$match->home_team} {$scoreStr} {$match->away_team} — typ: {$betStr}");
        }

        $totalPts = $participant->pointsTotal();

        $this->newLine();
        $this->info("Poprawnych typów: {$correct}/{$bets->count()}");
        $this->info("Łączne punkty: {$totalPts}");
        $this->info("Zaktualizowanych wierszy: {$updated}");

        return self::SUCCESS;
    }
}
