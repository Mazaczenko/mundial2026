<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Participant;
use App\Models\WorldMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class KnockoutController extends Controller
{
    private const STAGE_ORDER = ['r32', 'r16', 'qf', 'sf', '3rd_place', 'final'];

    public function index(): Response
    {
        /** @var Participant $participant */
        $participant = Auth::user();

        // Load all knockout matches
        $matches = WorldMatch::query()
            ->whereIn('stage', self::STAGE_ORDER)
            ->orderBy('kickoff_at')
            ->get();

        // My bets for knockout matches, keyed by match_id
        $myBets = Bet::where('participant_id', $participant->id)
            ->whereIn('match_id', $matches->pluck('id'))
            ->get()
            ->keyBy('match_id');

        // Bet stats per match (count per prediction_1x2)
        $betStats = Bet::whereIn('match_id', $matches->pluck('id'))
            ->selectRaw('match_id, prediction_1x2, COUNT(*) as cnt')
            ->groupBy('match_id', 'prediction_1x2')
            ->get()
            ->groupBy('match_id')
            ->map(function ($rows) {
                $data = ['1' => 0, 'X' => 0, '2' => 0];
                foreach ($rows as $row) {
                    $data[$row->prediction_1x2] = (int) $row->cnt;
                }
                $data['total'] = array_sum($data);

                return $data;
            });

        $mapped = $matches->map(function (WorldMatch $match) use ($myBets, $betStats) {
            $bet = $myBets->get($match->id);
            $stats = $betStats->get($match->id);

            return [
                'id' => $match->id,
                'home_team' => $match->home_team,
                'away_team' => $match->away_team,
                'home_team_flag' => $match->home_team_flag,
                'away_team_flag' => $match->away_team_flag,
                'kickoff_at' => $match->kickoff_at,
                'stage' => $match->stage,
                'status' => $match->status,
                'score_home' => $match->score_home,
                'score_away' => $match->score_away,
                'score_home_et' => $match->score_home_et,
                'score_away_et' => $match->score_away_et,
                'score_home_pen' => $match->score_home_pen,
                'score_away_pen' => $match->score_away_pen,
                'result_type' => $match->result_type,
                'my_bet' => $bet ? [
                    'prediction_1x2' => $bet->prediction_1x2,
                    'predicted_home' => $bet->predicted_home,
                    'predicted_away' => $bet->predicted_away,
                    'is_correct' => $bet->is_correct,
                ] : null,
                'bet_stats' => $stats,
            ];
        })->groupBy('stage');

        $matchesByStage = collect(self::STAGE_ORDER)
            ->mapWithKeys(fn ($s) => [$s => $mapped->get($s, collect())->values()])
            ->filter(fn ($s) => $s->isNotEmpty());

        $matchesByStage = $this->reorderForBracket($matchesByStage);

        return Inertia::render('Knockout/Index', [
            'matchesByStage' => $matchesByStage,
            'participant' => [
                'id' => $participant->id,
                'name' => $participant->name,
            ],
        ]);
    }

    /**
     * Reorder earlier-stage matches so that pairs [2j, 2j+1] correctly feed
     * the later-stage match at index j. Matching is done by winner team name.
     * Unresolved/TBD matches (no winner yet) are appended at the end.
     */
    private function reorderForBracket(Collection $matchesByStage): Collection
    {
        // Process from the latest stage backwards so that each earlier stage
        // is aligned to an already-aligned later stage.
        $pairs = [
            ['sf', 'final'],
            ['qf', 'sf'],
            ['r16', 'qf'],
            ['r32', 'r16'],
        ];

        foreach ($pairs as [$earlier, $later]) {
            if (! $matchesByStage->has($earlier) || ! $matchesByStage->has($later)) {
                continue;
            }

            /** @var Collection $earlierMatches */
            $earlierMatches = $matchesByStage->get($earlier);
            /** @var Collection $laterMatches */
            $laterMatches = $matchesByStage->get($later);

            // Index earlier matches by their winner team name for fast lookup.
            // Multiple unresolved matches may have null winner, so we track by match id.
            $byWinner = [];
            $unmatched = [];

            foreach ($earlierMatches as $match) {
                $winner = $this->getWinnerTeam($match);
                if ($winner !== null) {
                    $byWinner[$winner] = $match;
                } else {
                    $unmatched[] = $match;
                }
            }

            $reordered = [];

            foreach ($laterMatches as $laterMatch) {
                $homeFeeder = $byWinner[$laterMatch['home_team']] ?? null;
                $awayFeeder = $byWinner[$laterMatch['away_team']] ?? null;

                if ($homeFeeder !== null) {
                    $reordered[] = $homeFeeder;
                    unset($byWinner[$laterMatch['home_team']]);
                }

                if ($awayFeeder !== null) {
                    $reordered[] = $awayFeeder;
                    unset($byWinner[$laterMatch['away_team']]);
                }
            }

            // Append any earlier matches whose winner was not found in later stage
            // (covers TBD teams or data inconsistencies).
            foreach ($byWinner as $remaining) {
                $unmatched[] = $remaining;
            }

            $matchesByStage = $matchesByStage->put($earlier, collect(array_merge($reordered, $unmatched)));
        }

        return $matchesByStage;
    }

    /**
     * Return the winning team name for a match array, or null if the match is
     * not yet finished / the winner cannot be determined from available data.
     */
    private function getWinnerTeam(array $match): ?string
    {
        if (($match['status'] ?? null) !== 'finished') {
            return null;
        }

        $resultType = $match['result_type'] ?? null;
        $scoreHome = $match['score_home'] ?? null;
        $scoreAway = $match['score_away'] ?? null;
        $homePen = $match['score_home_pen'] ?? null;
        $awayPen = $match['score_away_pen'] ?? null;
        $homeEt = $match['score_home_et'] ?? null;
        $awayEt = $match['score_away_et'] ?? null;

        if ($resultType === 'PEN') {
            if ($homePen === null || $awayPen === null) {
                return null;
            }

            return $homePen > $awayPen ? $match['home_team'] : $match['away_team'];
        }

        if ($resultType === 'AET') {
            $homeTotal = ($scoreHome ?? 0) + ($homeEt ?? 0);
            $awayTotal = ($scoreAway ?? 0) + ($awayEt ?? 0);

            if ($homeTotal > $awayTotal) {
                return $match['home_team'];
            }

            if ($awayTotal > $homeTotal) {
                return $match['away_team'];
            }

            return null;
        }

        if ($scoreHome === null || $scoreAway === null) {
            return null;
        }

        if ($scoreHome > $scoreAway) {
            return $match['home_team'];
        }

        if ($scoreAway > $scoreHome) {
            return $match['away_team'];
        }

        // Draw in 90 min without AET/PEN — should not occur in knockout, but guard anyway.
        return null;
    }
}
