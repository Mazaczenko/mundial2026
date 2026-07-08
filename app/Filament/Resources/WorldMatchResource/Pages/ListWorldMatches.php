<?php

namespace App\Filament\Resources\WorldMatchResource\Pages;

use App\Filament\Resources\WorldMatchResource;
use App\Models\WorldMatch;
use App\Services\BetService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWorldMatches extends ListRecords
{
    protected static string $resource = WorldMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculate_pen_bets')
                ->label('Przelicz typy PEN')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Przelicz typy PEN')
                ->modalDescription('Akcja ponownie wyliczy is_correct dla wszystkich typów w meczach zakończonych rzutami karnymi (result_type = PEN). Wynik 90 min w takich meczach to zawsze remis (X).')
                ->modalSubmitActionLabel('Przelicz')
                ->action(function (BetService $betService): void {
                    $matches = WorldMatch::query()
                        ->where('status', 'finished')
                        ->where('result_type', 'PEN')
                        ->with('bets')
                        ->get();

                    foreach ($matches as $match) {
                        $betService->resolveBets($match);
                    }

                    Notification::make()
                        ->title('Typy PEN przeliczone')
                        ->body("Przeliczono typy dla {$matches->count()} meczów zakończonych karnym.")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
