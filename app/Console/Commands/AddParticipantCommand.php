<?php

namespace App\Console\Commands;

use App\Models\Participant;
use App\Notifications\WelcomeNotification;
use Illuminate\Console\Command;

class AddParticipantCommand extends Command
{
    protected $signature = 'mundial:add-participant
                            {name : Imię uczestnika}
                            {--password= : Hasło uczestnika}
                            {--phone= : Numer telefonu np. +48500123456}
                            {--admin : Ustaw jako administratora}';

    protected $description = 'Dodaj nowego uczestnika do typowania Mundial 2026';

    public function handle(): int
    {
        $name = $this->argument('name');
        $password = $this->option('password');
        $phone = $this->option('phone') ?: null;

        if (empty($password)) {
            $this->error('Hasło jest wymagane. Użyj --password=HASLO');

            return self::FAILURE;
        }

        $participant = Participant::create([
            'name' => $name,
            'password' => $password,
            'phone' => $phone,
            'is_admin' => (bool) $this->option('admin'),
        ]);

        $this->info("Uczestnik \"{$name}\" został dodany (ID: {$participant->id}).");

        if ($phone !== null) {
            $participant->notify(new WelcomeNotification($password));
            $this->info("Wysłano SMS powitalny na numer {$phone}.");
        }

        return self::SUCCESS;
    }
}
