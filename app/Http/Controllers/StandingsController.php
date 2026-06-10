<?php

namespace App\Http\Controllers;

use App\Models\GroupStanding;
use Inertia\Inertia;
use Inertia\Response;

class StandingsController extends Controller
{
    public function index(): Response
    {
        $standings = GroupStanding::query()
            ->orderBy('group_name')
            ->orderBy('position')
            ->get();

        return Inertia::render('Standings/Index', [
            'standings' => $standings->groupBy('group_name'),
        ]);
    }
}
