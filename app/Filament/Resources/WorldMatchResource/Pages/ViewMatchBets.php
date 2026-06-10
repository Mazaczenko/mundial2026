<?php

namespace App\Filament\Resources\WorldMatchResource\Pages;

use App\Filament\Resources\WorldMatchResource;
use App\Models\Participant;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMatchBets extends ViewRecord
{
    protected static string $resource = WorldMatchResource::class;

    protected static string $view = 'filament.resources.world-match-resource.pages.view-match-bets';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load(['bets.participant']);
    }

    public function getTitle(): string
    {
        return $this->record->home_team . ' vs ' . $this->record->away_team;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function getNotBettedParticipants(): \Illuminate\Support\Collection
    {
        $bettedIds = $this->record->bets->pluck('participant_id');

        return Participant::whereNotIn('id', $bettedIds)->orderBy('name')->get();
    }
}
