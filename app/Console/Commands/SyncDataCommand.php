<?php

namespace App\Console\Commands;

use App\Jobs\SyncStandingsJob;
use App\Jobs\SyncTopScorersJob;
use App\Services\FootballApiService;
use Illuminate\Console\Command;

class SyncDataCommand extends Command
{
    protected $signature = 'mundial:sync
                            {--standings : Synchronizuj tabele grup}
                            {--scorers : Synchronizuj top strzelców}
                            {--all : Synchronizuj wszystko}';

    protected $description = 'Ręczna synchronizacja danych z football-data.org';

    public function handle(FootballApiService $api): int
    {
        $all = $this->option('all');

        if ($all || $this->option('standings')) {
            $this->info('Synchronizacja tabel grup...');
            $job = new SyncStandingsJob();
            $job->handle($api);
            $count = \App\Models\GroupStanding::count();
            $this->info("Tabele zsynchronizowane ({$count} wpisów).");
        }

        if ($all || $this->option('scorers')) {
            $this->info('Synchronizacja top strzelców...');
            $job = new SyncTopScorersJob();
            $job->handle($api);
            $this->info('Top strzelcy zsynchronizowani.');
        }

        if (! $all && ! $this->option('standings') && ! $this->option('scorers')) {
            $this->warn('Podaj opcję: --standings, --scorers lub --all');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
