<?php

namespace App\Http\Controllers;

use App\Services\RankingService;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    public function __construct(
        private readonly RankingService $rankingService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Ranking/Index', [
            'ranking' => $this->rankingService->getRanking(),
        ]);
    }
}
