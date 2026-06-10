<?php

namespace App\Filament\Resources\WorldMatchResource\Pages;

use App\Filament\Resources\WorldMatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorldMatches extends ListRecords
{
    protected static string $resource = WorldMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
