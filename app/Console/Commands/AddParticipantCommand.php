<?php

namespace App\Console\Commands;

use App\Models\Participant;
use App\Notifications\WelcomeNotification;
use Illuminate\Console\Command;

class AddParticipantCommand extends Command
{
    protected $signature = 'mundial:add-participant
                            {name : Imię uczestnika}
                            {--pin= : 4-6 cyfrowy PIN}
                            {--phone= : Numer telefonu np. +48500123456}
                            {--admin : Ustaw jako administratora}';

    protected $description = 'Dodaj nowego uczestnika do typowania Mundial 2026';

    public function handle(): int
    {
        $name = $this->argument('name');
        $pin = $this->option('pin');
        $phone = $this->option('phone') ?: null;

        if (empty($pin)) {
            $this->error('PIN jest wymagany. Użyj --pin=XXXX');

            return self::FAILURE;
        }

        if (! preg_match('/^\d{4,6}$/', $pin)) {
            $this->error('PIN musi mieć 4-6 cyfr.');

            return self::FAILURE;
        }

        $participant = Participant::create([
            'name' => $name,
            'pin' => $pin,
            'phone' => $phone,
            'is_admin' => (bool) $this->option('admin'),
        ]);

        $this->info("Uczestnik \"{$name}\" został dodany (ID: {$participant->id}).");

        if ($phone !== null) {
            $participant->notify(new WelcomeNotification($pin));
            $this->info("Wysłano SMS powitalny na numer {$phone}.");
        }

        return self::SUCCESS;
    }
}
