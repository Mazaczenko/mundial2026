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
                ->label('Przelicz typy AET/PEN')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Przelicz typy AET/PEN')
                ->modalDescription('Akcja ponownie wyliczy is_correct dla wszystkich typów w meczach zakończonych dogrywką (AET) lub rzutami karnymi (PEN). Typujemy wynik po 90 minutach.')
                ->modalSubmitActionLabel('Przelicz')
                ->action(function (BetService $betService): void {
                    $matches = WorldMatch::query()
                        ->where('status', 'finished')
                        ->whereIn('result_type', ['AET', 'PEN'])
                        ->with('bets')
                        ->get();

                    foreach ($matches as $match) {
                        $betService->resolveBets($match);
                    }

                    Notification::make()
                        ->title('Typy AET/PEN przeliczone')
                        ->body("Przeliczono typy dla {$matches->count()} meczów zakończonych dogrywką lub karnym.")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
