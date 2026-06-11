<?php

namespace Database\Seeders;

use App\Models\Participant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            [
                'name'     => 'Wojtek Mazur',
                'email'    => 'w.mazur@fortis.pl',
                'password' => 'Fortis10062026',
                'is_admin' => true,
            ],
            [
                'name'     => 'Tomek Ciechański',
                'email'    => 't.ciechanski@fortis.pl',
                'password' => 'Fortis10062026',
                'is_admin' => true,
            ],
        ];

        foreach ($admins as $data) {
            Participant::updateOrCreate(
                ['email' => $data['email']],
                $data,
            );
        }

        $this->command->info('Admini dodani: ' . implode(', ', array_column($admins, 'email')));
    }
}

