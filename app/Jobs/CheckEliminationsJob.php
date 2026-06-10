<?php

namespace App\Jobs;

use App\Services\EliminationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckEliminationsJob implements ShouldQueue
{
    use Queueable;

    public function handle(EliminationService $eliminationService): void
    {
        $eliminationService->checkAll();
    }
}
