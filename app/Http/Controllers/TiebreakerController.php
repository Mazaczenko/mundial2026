<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTiebreakerRequest;
use App\Models\Participant;
use App\Models\TiebreakerPick;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TiebreakerController extends Controller
{
    public function show(): Response
    {
        /** @var Participant $user */
        $user = Auth::user();

        return Inertia::render('Tiebreaker/Show', [
            'pick' => $user->tiebreakerPick,
            'deadline' => Carbon::parse('2026-06-11 18:00:00', 'UTC')->toIso8601String(),
        ]);
    }

    public function store(StoreTiebreakerRequest $request): RedirectResponse
    {
        /** @var Participant $user */
        $user = Auth::user();

        TiebreakerPick::updateOrCreate(
            ['participant_id' => $user->id],
            [
                'top_scorer_name' => $request->validated()['top_scorer_name'],
                'submitted_at' => Carbon::now(),
            ]
        );

        return redirect()->back()->with('success', 'Tiebreaker zapisany!');
    }
}
