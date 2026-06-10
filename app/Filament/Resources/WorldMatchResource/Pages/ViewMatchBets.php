<?php

namespace App\Filament\Resources\WorldMatchResource\Pages;

use App\Filament\Resources\WorldMatchResource;
use App\Models\WorldMatch;
use Filament\Resources\Pages\Page;

class ViewMatchBets extends Page
{
    protected static string $resource = WorldMatchResource::class;

    protected static string $view = 'filament.resources.world-match-resource.pages.view-match-bets';

    public WorldMatch $record;

    public function mount(int|string $record): void
    {
        $this->record = WorldMatch::with(['bets.participant'])->findOrFail($record);
    }

    public function getTitle(): string
    {
        return $this->record->home_team.' vs '.$this->record->away_team;
    }
}
