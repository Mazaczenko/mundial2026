<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBetRequest;
use App\Http\Requests\UpdateBetRequest;
use App\Models\Bet;
use App\Models\Participant;
use App\Models\WorldMatch;
use App\Services\BetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BetController extends Controller
{
    public function __construct(
        private readonly BetService $betService,
    ) {}

    public function index(Request $request): Response
    {
        /** @var Participant $user */
        $user = Auth::user();

        $tab = $request->input('tab', 'today');
        if (! in_array($tab, ['today', 'upcoming', 'past'])) {
            $tab = 'today';
        }

        $team = (string) $request->input('team', '');
        $bet = (string) $request->input('bet', '');
        $result = (string) $request->input('result', '');

        $warsawNow = Carbon::now('Europe/Warsaw');
        $todayStart = $warsawNow->copy()->startOfDay()->utc();
        $todayEnd = $warsawNow->copy()->endOfDay()->utc();

        // Tab counts (no team/bet/result filters)
        $tabCounts = [
            'today' => WorldMatch::query()
                ->whereBetween('kickoff_at', [$todayStart, $todayEnd])
                ->count(),
            'upcoming' => WorldMatch::query()
                ->where('kickoff_at', '>', $todayEnd)
                ->count(),
            'past' => WorldMatch::query()
                ->where('kickoff_at', '<', $todayStart)
                ->count(),
        ];

        // Base query for the selected tab
        $query = WorldMatch::query()
            ->with([
                'bets' => fn ($q) => $q->with('participant:id,name'),
                'goals' => fn ($q) => $q->orderByRaw('CAST(minute AS UNSIGNED)'),
            ]);

        match ($tab) {
            'today' => $query->whereBetween('kickoff_at', [$todayStart, $todayEnd])
                ->orderBy('kickoff_at'),
            'upcoming' => $query->where('kickoff_at', '>', $todayEnd)
                ->orderBy('kickoff_at'),
            'past' => $query->where('kickoff_at', '<', $todayStart)
                ->orderByDesc('kickoff_at'),
        };

        // Team filter
        if ($team !== '') {
            $query->where(function ($q) use ($team) {
                $q->where('home_team', 'like', '%'.$team.'%')
                    ->orWhere('away_team', 'like', '%'.$team.'%');
            });
        }

        // Bet filter (DB-level)
        if ($bet === 'placed') {
            $query->whereHas('bets', fn ($q) => $q->where('participant_id', $user->id));
        } elseif ($bet === 'missing') {
            $query->whereDoesntHave('bets', fn ($q) => $q->where('participant_id', $user->id));
        } elseif (in_array($bet, ['1', 'X', '2'])) {
            $query->whereHas('bets', fn ($q) => $q
                ->where('participant_id', $user->id)
                ->where('prediction_1x2', $bet));
        }

        $matches = $query->get();

        // Map matches to MatchData
        $mapped = $matches->map(function (WorldMatch $match) use ($user) {
            $myBet = $match->bets->firstWhere('participant_id', $user->id);
            $canBet = $match->canBet();
            $isVisible = ! $canBet;

            $othersBets = $isVisible
                ? $match->bets
                    ->where('participant_id', '!=', $user->id)
                    ->map(fn ($b) => [
                        'participant_name' => $b->participant->name,
                        'prediction_1x2' => $b->prediction_1x2,
                    ])
                    ->values()
                : collect();

            $betStats = null;
            if ($isVisible && $match->bets->isNotEmpty()) {
                $total = $match->bets->count();
                $counts = $match->bets->countBy('prediction_1x2');
                $betStats = [
                    '1' => round(($counts['1'] ?? 0) / $total * 100),
                    'X' => round(($counts['X'] ?? 0) / $total * 100),
                    '2' => round(($counts['2'] ?? 0) / $total * 100),
                    'total' => $total,
                ];
            }

            return [
                'id' => $match->id,
                'home_team' => $match->home_team,
                'away_team' => $match->away_team,
                'home_team_flag' => $match->home_team_flag,
                'away_team_flag' => $match->away_team_flag,
                'kickoff_at' => $match->kickoff_at->toIso8601String(),
                'stage' => $match->stage,
                'group_name' => $match->group_name,
                'status' => $match->status,
                'score_home' => $match->score_home,
                'score_away' => $match->score_away,
                'can_bet' => $canBet,
                'my_bet' => $myBet ? [
                    'id' => $myBet->id,
                    'prediction_1x2' => $myBet->prediction_1x2,
                    'predicted_home' => $myBet->predicted_home,
                    'predicted_away' => $myBet->predicted_away,
                    'is_correct' => $myBet->is_correct,
                ] : null,
                'others_bets' => $othersBets,
                'bet_stats' => $betStats,
                'goals' => $match->goals->map(fn ($g) => [
                    'player_name' => $g->player_name,
                    'team_side' => $g->team_side,
                    'minute' => $g->minute,
                    'own_goal' => $g->own_goal,
                ])->values(),
            ];
        });

        // Result filter (PHP-level)
        if ($result === 'correct') {
            $mapped = $mapped->filter(fn ($m) => ($m['my_bet']['is_correct'] ?? null) === true);
        } elseif ($result === 'wrong') {
            $mapped = $mapped->filter(fn ($m) => ($m['my_bet']['is_correct'] ?? null) === false);
        } elseif ($result === 'pending') {
            $mapped = $mapped->filter(fn ($m) => $m['my_bet'] !== null && ($m['my_bet']['is_correct'] ?? null) === null);
        }

        // Group by date (Warsaw timezone)
        $matchesByDate = $mapped->groupBy(function ($match) {
            return Carbon::parse($match['kickoff_at'])
                ->setTimezone('Europe/Warsaw')
                ->format('Y-m-d');
        });

        // All teams for the filter select
        $teams = WorldMatch::selectRaw('home_team as team')
            ->union(WorldMatch::selectRaw('away_team as team'))
            ->orderBy('team')
            ->pluck('team')
            ->unique()
            ->values();

        return Inertia::render('Bets/Index', [
            'matchesByDate' => $matchesByDate,
            'tab' => $tab,
            'tabCounts' => $tabCounts,
            'filters' => compact('team', 'bet', 'result'),
            'teams' => $teams,
            'participant' => $user,
        ]);
    }

    public function store(StoreBetRequest $request): RedirectResponse
    {
        /** @var Participant $user */
        $user = Auth::user();

        $this->betService->placeBet($user, $request->validated());

        return redirect()->back()->with('success', 'Typ zapisany!');
    }

    public function update(UpdateBetRequest $request, Bet $bet): RedirectResponse
    {
        $this->betService->updateBet($bet, $request->validated());

        return redirect()->back()->with('success', 'Typ zaktualizowany!');
    }
}
