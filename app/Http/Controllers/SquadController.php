<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Inertia\Inertia;
use Inertia\Response;

class SquadController extends Controller
{
    public function index(): Response
    {
        $squads = Player::orderBy('team_name')->orderByRaw("FIELD(position,'Goalkeeper','Defence','Midfield','Offence')")->get()
            ->groupBy('team_name')
            ->map(fn ($players) => $players->groupBy('position'));

        return Inertia::render('Squads/Index', ['squads' => $squads]);
    }
}
