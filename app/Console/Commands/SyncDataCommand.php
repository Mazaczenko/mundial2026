<?php

namespace App\Console\Commands;

use App\Jobs\SyncStandingsJob;
use App\Jobs\SyncTopScorersJob;
use App\Models\GroupStanding;
use App\Services\FootballApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncDataCommand extends Command
{
    protected $signature = 'mundial:sync
                            {--standings : Synchronizuj tabele grup}
                            {--scorers : Synchronizuj top strzelców}
                            {--players : Synchronizuj składy drużyn}
                            {--all : Synchronizuj wszystko}';

    protected $description = 'Ręczna synchronizacja danych z football-data.org';

    public function handle(FootballApiService $api): int
    {
        $all = $this->option('all');

        if ($all || $this->option('standings')) {
            $this->info('Synchronizacja tabel grup...');
            $job = new SyncStandingsJob;
            $job->handle($api);
            $count = GroupStanding::count();
            $this->info("Tabele zsynchronizowane ({$count} wpisów).");
        }

        if ($all || $this->option('scorers')) {
            $this->info('Synchronizacja top strzelców...');
            $job = new SyncTopScorersJob;
            $job->handle($api);
            $this->info('Top strzelcy zsynchronizowani.');
        }

        if ($all || $this->option('players')) {
            $this->info('Synchronizacja składów drużyn...');
            Artisan::call('mundial:sync-players', [], $this->getOutput());
        }

        if (! $all && ! $this->option('standings') && ! $this->option('scorers') && ! $this->option('players')) {
            $this->warn('Podaj opcję: --standings, --scorers, --players lub --all');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
