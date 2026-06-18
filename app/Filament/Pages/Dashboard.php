<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Symfony\Component\Process\Process;

class Dashboard extends \Filament\Pages\Dashboard
{
    private function runCommand(array $command, string $label): void
    {
        $process = new Process(command: $command, cwd: base_path(), timeout: 300);
        $process->run();

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        if (mb_strlen($output) > 2000) {
            $output = mb_substr($output, 0, 2000)."\n…(output truncated)";
        }

        if ($process->isSuccessful()) {
            Notification::make()
                ->title($label.' zakończone')
                ->body($output ?: 'Brak danych wyjściowych.')
                ->success()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title($label.' nie powiodło się (kod: '.$process->getExitCode().')')
                ->body($output ?: 'Brak danych wyjściowych.')
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Synchronizuj dane')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Synchronizacja danych')
                ->modalDescription('Uruchomi komendę mundial:sync --all. Operacja może potrwać kilka minut.')
                ->modalSubmitActionLabel('Uruchom')
                ->action(fn () => $this->runCommand(['php84-cli', 'artisan', 'mundial:sync', '--all'], 'Synchronizacja')),

            Action::make('resolve_bets')
                ->label('Rozlicz typy')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Rozliczenie typów')
                ->modalDescription('Uruchomi komendę mundial:resolve-bets.')
                ->modalSubmitActionLabel('Uruchom')
                ->action(fn () => $this->runCommand(['php84-cli', 'artisan', 'mundial:resolve-bets'], 'Rozliczenie typów')),

            Action::make('backfill_details')
                ->label('Backfill kartki/składy/statystyki')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Backfill szczegółów meczów')
                ->modalDescription('Pobierze z ESPN kartki, statystyki i składy dla wszystkich zakończonych meczów, którym brakuje tych danych.')
                ->modalSubmitActionLabel('Uruchom')
                ->action(fn () => $this->runCommand(['php84-cli', 'artisan', 'mundial:backfill-details'], 'Backfill szczegółów')),
        ];
    }
}
