<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParticipantResource\Pages;
use App\Models\Participant;
use App\Services\SmsService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ParticipantResource extends Resource
{
    protected static ?string $model = Participant::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Uczestnicy';

    protected static ?string $modelLabel = 'Uczestnik';

    protected static ?string $pluralModelLabel = 'Uczestnicy';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Imię i nazwisko')
                    ->required()
                    ->maxLength(100),

                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->nullable()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Wymagany do logowania do panelu admina'),

                TextInput::make('password')
                    ->label('Hasło')
                    ->password()
                    ->minLength(4)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->hint(fn (string $operation) => $operation === 'edit' ? 'Zostaw puste, aby nie zmieniać' : null),

                TextInput::make('phone')
                    ->label('Telefon')
                    ->nullable()
                    ->maxLength(20),

                Toggle::make('paid_entry')
                    ->label('Wpłata 10 zł'),

                Toggle::make('sms_notifications')
                    ->label('Powiadomienia SMS'),

                Toggle::make('eliminated')
                    ->label('Wyeliminowany'),

                Toggle::make('is_admin')
                    ->label('Administrator'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Imię i nazwisko')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('points')
                    ->label('Punkty')
                    ->getStateUsing(fn (Participant $record) => $record->pointsTotal())
                    ->badge()
                    ->color('success'),

                TextColumn::make('bets_count')
                    ->label('Typy')
                    ->getStateUsing(fn (Participant $record) => $record->bets()->count()),

                TextColumn::make('missed_matches')
                    ->label('Pominięte')
                    ->getStateUsing(fn (Participant $record) => $record->missedMatchesCount())
                    ->color(fn (Participant $record) => $record->missedMatchesCount() >= 3 ? 'danger' : null),

                IconColumn::make('paid_entry')
                    ->label('Wpłata 💰')
                    ->boolean(),

                IconColumn::make('eliminated')
                    ->label('Eliminacja')
                    ->boolean()
                    ->trueColor('danger'),

                TextColumn::make('phone')
                    ->label('Telefon')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('paid_entry')
                    ->label('Wpłata')
                    ->options(['1' => 'Wpłacili']),

                TernaryFilter::make('eliminated')
                    ->label('Eliminacja')
                    ->falseLabel('Aktywni'),
            ])
            ->actions([
                EditAction::make(),

                Action::make('send_password_sms')
                    ->label('Wyślij hasło SMS')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('info')
                    ->visible(fn (Participant $record) => filled($record->phone))
                    ->requiresConfirmation()
                    ->modalHeading('Wyślij SMS z instrukcją logowania')
                    ->modalDescription(fn (Participant $record) => "Wyślij SMS do {$record->name} ({$record->phone}) z instrukcją, jak uzyskać PIN.")
                    ->action(function (Participant $record): void {
                        $smsService = app(SmsService::class);
                        $message = 'Mundial 2026: Twoj PIN do systemu typowania jest przechowywany przez administratora. Skontaktuj sie z adminem po swoj PIN. Zaloguj sie na: '.config('app.url');

                        $success = $smsService->send($record->phone, $message);

                        if ($success) {
                            Notification::make()
                                ->title('SMS wysłany')
                                ->body("Wiadomość SMS została wysłana do {$record->name}.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Błąd wysyłki SMS')
                                ->body("Nie udało się wysłać SMS do {$record->name}. Sprawdź logi.")
                                ->danger()
                                ->send();
                        }
                    }),

                DeleteAction::make()
                    ->visible(fn (Participant $record) => $record->id !== Auth::id()),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParticipants::route('/'),
            'create' => Pages\CreateParticipant::route('/create'),
            'edit' => Pages\EditParticipant::route('/{record}/edit'),
        ];
    }
}
