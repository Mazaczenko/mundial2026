<?php

namespace App\Filament\Resources\WorldMatchResource\Pages;

use App\Filament\Resources\WorldMatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorldMatch extends EditRecord
{
    protected static string $resource = WorldMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
