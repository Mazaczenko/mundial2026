<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorldMatchResource\Pages;
use App\Models\WorldMatch;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorldMatchResource extends Resource
{
    protected static ?string $model = WorldMatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Mecze';

    protected static ?string $modelLabel = 'Mecz';

    protected static ?string $pluralModelLabel = 'Mecze';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('home_team')
                    ->label('Gospodarz')
                    ->required(),

                TextInput::make('away_team')
                    ->label('Gość')
                    ->required(),

                DateTimePicker::make('kickoff_at')
                    ->label('Kick-off')
                    ->required()
                    ->timezone('Europe/Warsaw'),

                Select::make('stage')
                    ->label('Etap')
                    ->options([
                        'group' => 'Faza grupowa',
                        'r32' => '1/32',
                        'r16' => '1/16',
                        'qf' => 'Ćwierćfinał',
                        'sf' => 'Półfinał',
                        'final' => 'Finał',
                    ]),

                TextInput::make('group_name')
                    ->label('Grupa')
                    ->nullable(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'finished' => 'Finished',
                    ]),

                TextInput::make('score_home')
                    ->label('Wynik — gospodarz')
                    ->numeric()
                    ->nullable(),

                TextInput::make('score_away')
                    ->label('Wynik — gość')
                    ->numeric()
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kickoff_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i', 'Europe/Warsaw')
                    ->sortable(),

                TextColumn::make('group_name')
                    ->label('Gr.'),

                TextColumn::make('stage')
                    ->label('Etap')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'group' => 'gray',
                        'r32' => 'info',
                        'r16' => 'info',
                        'qf' => 'warning',
                        'sf' => 'warning',
                        'final' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'group' => 'Grupowa',
                        'r32' => '1/32',
                        'r16' => '1/16',
                        'qf' => 'Ćwierćfinał',
                        'sf' => 'Półfinał',
                        'final' => 'Finał',
                        default => $state,
                    }),

                TextColumn::make('match')
                    ->label('Mecz')
                    ->getStateUsing(fn (WorldMatch $record) => $record->home_team.' vs '.$record->away_team)
                    ->searchable(query: function ($query, string $search) {
                        $query->where('home_team', 'like', "%{$search}%")
                            ->orWhere('away_team', 'like', "%{$search}%");
                    }),

                TextColumn::make('score')
                    ->label('Wynik')
                    ->getStateUsing(fn (WorldMatch $record) => $record->score_home !== null ? "{$record->score_home}:{$record->score_away}" : '–')
                    ->badge()
                    ->color(fn (WorldMatch $record) => $record->status === 'finished' ? 'success' : 'gray'),

                TextColumn::make('bets_count')
                    ->label('Typy')
                    ->getStateUsing(fn (WorldMatch $record) => $record->bets()->count()),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => $state === 'finished' ? 'success' : 'warning'),
            ])
            ->filters([
                SelectFilter::make('stage')
                    ->label('Etap')
                    ->options([
                        'group' => 'Faza grupowa',
                        'r32' => '1/32',
                        'r16' => '1/16',
                        'qf' => 'Ćwierćfinał',
                        'sf' => 'Półfinał',
                        'final' => 'Finał',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'finished' => 'Finished',
                    ]),
            ])
            ->actions([
                Action::make('view_bets')
                    ->label('Typy')
                    ->icon('heroicon-o-eye')
                    ->url(fn (WorldMatch $record) => Pages\ViewMatchBets::getUrl(['record' => $record->id])),

                EditAction::make(),
            ])
            ->defaultSort('kickoff_at');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorldMatches::route('/'),
            'edit' => Pages\EditWorldMatch::route('/{record}/edit'),
            'view-bets' => Pages\ViewMatchBets::route('/{record}/bets'),
        ];
    }
}
