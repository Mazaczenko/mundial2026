<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Symfony\Component\Process\Process;

class Dashboard extends \Filament\Pages\Dashboard
{
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
                ->action(function (): void {
                    $process = new Process(
                        command: ['php84-cli', 'artisan', 'mundial:sync', '--all'],
                        cwd: base_path(),
                        timeout: 300,
                    );

                    $process->run();

                    $output = trim($process->getOutput()."\n".$process->getErrorOutput());
                    $output = trim($output);

                    if (mb_strlen($output) > 2000) {
                        $output = mb_substr($output, 0, 2000)."\n…(output truncated)";
                    }

                    if ($process->isSuccessful()) {
                        Notification::make()
                            ->title('Synchronizacja zakończona')
                            ->body($output ?: 'Brak danych wyjściowych.')
                            ->success()
                            ->persistent()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Synchronizacja nie powiodła się (kod: '.$process->getExitCode().')')
                            ->body($output ?: 'Brak danych wyjściowych.')
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
