<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Participant;
use App\Models\WorldMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MatchResultsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Participant $participant */
        $participant = Auth::user();

        $teamFilter   = $request->input('team', '');
        $stageFilter  = $request->input('stage', '');
        $resultFilter = $request->input('result', '');

        $allowedPerPage = [5, 10, 15, 20, 25, 50];
        $perPage = in_array((int) $request->input('per_page', 10), $allowedPerPage)
            ? (int) $request->input('per_page', 10)
            : 10;
        $page = max(1, (int) $request->input('page', 1));

        // --- Global stats (always unfiltered) ---
        $allFinishedIds = WorldMatch::finished()->pluck('id');

        $myBetsOnFinished = Bet::where('participant_id', $participant->id)
            ->whereIn('match_id', $allFinishedIds)
            ->get();

        $myCorrect = $myBetsOnFinished->where('is_correct', true)->count();
        $myTotal   = $myBetsOnFinished->count();

        $stats = [
            'total_matches' => $allFinishedIds->count(),
            'my_correct'    => $myCorrect,
            'my_total'      => $myTotal,
            'my_missed'     => $allFinishedIds->count() - $myTotal,
            'my_accuracy'   => $myTotal > 0 ? round($myCorrect / $myTotal * 100) : null,
            'streak'        => $this->computeStreak($participant->id, $allFinishedIds->toArray()),
        ];

        // --- Filtered matches ---
        $query = WorldMatch::with([
            'goals' => fn ($q) => $q->orderByRaw('CAST(minute AS UNSIGNED)'),
            'bets',
        ])
            ->finished()
            ->orderBy('kickoff_at', 'desc');

        if ($teamFilter) {
            $query->where(fn ($q) => $q
                ->where('home_team', $teamFilter)
                ->orWhere('away_team', $teamFilter)
            );
        }

        if ($stageFilter === 'group') {
            $query->where('stage', 'group');
        } elseif ($stageFilter === 'knockout') {
            $query->whereIn('stage', ['r32', 'r16', 'qf', 'sf', 'final']);
        }

        $matches = $query->get();

        $myBets = Bet::where('participant_id', $participant->id)
            ->whereIn('match_id', $matches->pluck('id'))
            ->get()
            ->keyBy('match_id');

        if ($resultFilter === 'correct') {
            $matches = $matches->filter(fn ($m) => ($myBets[$m->id] ?? null)?->is_correct === true);
        } elseif ($resultFilter === 'wrong') {
            $matches = $matches->filter(fn ($m) => ($myBets[$m->id] ?? null)?->is_correct === false);
        } elseif ($resultFilter === 'missed') {
            $matches = $matches->filter(fn ($m) => ! isset($myBets[$m->id]));
        }

        $matchData = $matches->map(function (WorldMatch $match) use ($myBets) {
            $myBet = $myBets[$match->id] ?? null;

            return [
                'id'                 => $match->id,
                'home_team'          => $match->home_team,
                'away_team'          => $match->away_team,
                'home_team_flag'     => $match->home_team_flag,
                'away_team_flag'     => $match->away_team_flag,
                'kickoff_at'         => $match->kickoff_at,
                'stage'              => $match->stage,
                'group_name'         => $match->group_name,
                'score_home'         => $match->score_home,
                'score_away'         => $match->score_away,
                'result_type'        => $match->result_type,
                'correct_bets' => $match->bets->where('is_correct', true)->count(),
                'total_bets'   => $match->bets->count(),
                'my_bet'             => $myBet ? [
                    'prediction_1x2' => $myBet->prediction_1x2,
                    'is_correct'     => $myBet->is_correct,
                ] : null,
                'goals' => $match->goals->map(fn ($g) => [
                    'player_name' => $g->player_name,
                    'team_side'   => $g->team_side,
                    'minute'      => $g->minute,
                    'own_goal'    => $g->own_goal,
                ])->values(),
            ];
        })->values();

        // --- Paginate ---
        $total    = $matchData->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = min($page, $lastPage);

        $pagination = [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
            'from'         => $total > 0 ? ($page - 1) * $perPage + 1 : 0,
            'to'           => min($page * $perPage, $total),
        ];

        $matchData = $matchData->forPage($page, $perPage)->values();

        // --- Teams for filter dropdown ---
        $teams = WorldMatch::finished()
            ->get(['home_team', 'away_team'])
            ->flatMap(fn ($m) => [$m->home_team, $m->away_team])
            ->unique()
            ->sort()
            ->values()
            ->all();

        // --- Top scorers from local DB (non-own-goals only) ---
        $topScorers = DB::table('match_goals')
            ->join('world_matches', 'world_matches.id', '=', 'match_goals.world_match_id')
            ->where('world_matches.status', 'finished')
            ->where('match_goals.own_goal', false)
            ->select('match_goals.player_name', DB::raw('COUNT(*) as goals'))
            ->groupBy('match_goals.player_name')
            ->orderByDesc('goals')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['name' => $r->player_name, 'goals' => (int) $r->goals]);

        return Inertia::render('Results/Index', [
            'matches'    => $matchData,
            'stats'      => $stats,
            'teams'      => $teams,
            'topScorers' => $topScorers,
            'pagination' => $pagination,
            'filters'    => [
                'team'     => $teamFilter,
                'stage'    => $stageFilter,
                'result'   => $resultFilter,
                'per_page' => $perPage,
            ],
        ]);
    }

    private function computeStreak(int $participantId, array $finishedIds): int
    {
        if (empty($finishedIds)) {
            return 0;
        }

        $bets = Bet::where('participant_id', $participantId)
            ->whereIn('match_id', $finishedIds)
            ->join('world_matches', 'world_matches.id', '=', 'bets.match_id')
            ->orderByDesc('world_matches.kickoff_at')
            ->select('bets.is_correct')
            ->get();

        $streak = 0;
        foreach ($bets as $bet) {
            if ($bet->is_correct === true) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }
}
