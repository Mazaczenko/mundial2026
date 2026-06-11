<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SquadController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request): Response
    {
        $search = trim($request->input('search', ''));
        $page = max(1, (int) $request->input('page', 1));

        $teamsQuery = Player::query()
            ->when($search, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('team_name', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
            ))
            ->distinct()
            ->orderBy('team_name')
            ->pluck('team_name');

        $total = $teamsQuery->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $lastPage);
        $teamNames = $teamsQuery->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values();

        $players = Player::whereIn('team_name', $teamNames)
            ->when($search, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('team_name', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
            ))
            ->orderBy('team_name')
            ->orderByRaw("FIELD(position,'Goalkeeper','Defence','Midfield','Offence')")
            ->get();

        $crests = $players->groupBy('team_name')->map(fn ($g) => $g->first()->team_crest);

        $squads = $players->groupBy('team_name')
            ->map(fn ($teamPlayers) => $teamPlayers->groupBy('position'));

        return Inertia::render('Squads/Index', [
            'squads' => $squads,
            'crests' => $crests,
            'filters' => ['search' => $search],
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'total' => $total,
            ],
        ]);
    }
}
