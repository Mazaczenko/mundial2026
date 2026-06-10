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
                            {--email= : Adres email (do powiadomień i logowania admina)}
                            {--admin : Ustaw jako administratora}';

    protected $description = 'Dodaj nowego uczestnika do typowania Mundial 2026';

    public function handle(): int
    {
        $name = $this->argument('name');
        $password = $this->option('password');
        $email = $this->option('email') ?: null;

        if (empty($password)) {
            $this->error('Hasło jest wymagane. Użyj --password=HASLO');

            return self::FAILURE;
        }

        $participant = Participant::create([
            'name'     => $name,
            'password' => $password,
            'email'    => $email,
            'is_admin' => (bool) $this->option('admin'),
        ]);

        $this->info("Uczestnik \"{$name}\" został dodany (ID: {$participant->id}).");

        if ($email !== null) {
            $participant->notify(new WelcomeNotification($password));
            $this->info("Wysłano email powitalny na adres {$email}.");
        }

        return self::SUCCESS;
    }
}
