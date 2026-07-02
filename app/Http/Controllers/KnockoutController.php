<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Participant;
use App\Models\WorldMatch;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class KnockoutController extends Controller
{
    private const STAGE_ORDER = ['r32', 'r16', 'qf', 'sf', 'final'];

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

        return Inertia::render('Knockout/Index', [
            'matchesByStage' => $matchesByStage,
            'participant' => [
                'id' => $participant->id,
                'name' => $participant->name,
            ],
        ]);
    }
}
