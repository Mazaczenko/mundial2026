<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerResource\Pages;
use App\Models\Player;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PlayerResource extends Resource
{
    protected static ?string $model = Player::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Składy';

    protected static ?string $modelLabel = 'Zawodnik';

    protected static ?string $pluralModelLabel = 'Zawodnicy';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nazwisko')
                    ->searchable(),

                TextColumn::make('position')
                    ->label('Pozycja')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Goalkeeper' => 'warning',
                        'Defence' => 'info',
                        'Midfield' => 'success',
                        'Offence' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('team_name')
                    ->label('Drużyna')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('position')
                    ->label('Pozycja')
                    ->options([
                        'Goalkeeper' => 'Bramkarz',
                        'Defence' => 'Obrońca',
                        'Midfield' => 'Pomocnik',
                        'Offence' => 'Napastnik',
                    ]),

                SelectFilter::make('team_name')
                    ->label('Drużyna')
                    ->options(
                        Player::distinct('team_name')
                            ->orderBy('team_name')
                            ->pluck('team_name', 'team_name')
                    ),
            ])
            ->defaultSort('team_name')
            ->defaultSort('position');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayers::route('/'),
        ];
    }
}
