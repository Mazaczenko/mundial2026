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
    // Piątek 12.06.2026 23:59 czasu warszawskiego (CEST = UTC+2)
    public const DEADLINE = '2026-06-12 21:59:59';

    private const ALLOWED = ['m.procki', 'm.wawer', 'k.sochacki', 'p.wronowski'];

    public function show(): Response
    {
        /** @var Participant $user */
        $user = Auth::user();

        $allowed = in_array($user->name, self::ALLOWED, true) && Carbon::now('UTC')->lt(self::DEADLINE);

        return Inertia::render('Tiebreaker/Show', [
            'pick'     => $user->tiebreakerPick,
            'deadline' => Carbon::parse(self::DEADLINE, 'UTC')->toIso8601String(),
            'allowed'  => $allowed,
        ]);
    }

    public function store(StoreTiebreakerRequest $request): RedirectResponse
    {
        /** @var Participant $user */
        $user = Auth::user();

        abort_unless(
            in_array($user->name, self::ALLOWED, true) && Carbon::now('UTC')->lt(self::DEADLINE),
            403
        );

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
