<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParticipantsSeeder extends Seeder
{
    protected $emails = [
        'u.markuszewska@fortis.pl',
        'w.kratochwil@fortis.pl',
        'a.slapinski@fortis.pl',
        'a.krzeczkowska@fortis.pl',
        'm.wawer@fortis.pl',
        'a.acher@fortis.pl',
        'm.kieszek@fortis.pl',
        'a.jarzecki@fortis.pl',
        't.kedziora@fortis.pl',
        'b.siwek@fortis.pl',
        'p.dawiec@fortis.pl',
        'k.papuga@fortis.pl',
        'p.liszewski@fortis.pl',
        't.lugowski@fortis.pl',
        'm.procki@fortis.pl',
        'mirek@fortis.pl',
        'd.dawiec@fortis.pl',
        'j.lakomiec@fortis.pl',
        'm.piorkowska@fortis.pl',
        'k.owczarek@fortis.pl',
        'm.pogorzelski@fortis.pl',
        'm.megger@fortis.pl',
        'p.mitoraj@fortis.pl',
        'j.augustyniak@fortis.pl',
        'j.machorowska@fortis.pl',
        'a.burawska@fortis.pl',
        'e.budnicka@fortis.pl',
        'd.kubiszewski@fortis.pl',
        'm.peter-szymanska@fortis.pl',
        'a.lankau@fortis.pl',
        'm.turek@fortis.pl',
        'y.dorokhova@fortis.pl',
        'k.piorkowski@fortis.pl',
        'm.kiedo@fortis.pl',
        's.gizka@fortis.pl',
        'm.tuszynski@fortis.pl',
        'e.staniucha@fortis.pl',
        'm.grajek@fortis.pl',
        'l.kuzmowych@fortis.pl',
        'k.lipiec@fortis.pl',
        'm.krajewska@fortis.pl',
        'k.sak@fortis.pl',
        'p.lapinski@fortis.pl',
        'k.musial@fortis.pl',
        'm.tworkowska@fortis.pl',
        'o.dros@fortis.pl',
        'm.sliwa@fortis.pl',
        'h.herasimava@fortis.pl',
        'i.kuzmowych@fortis.pl',
        't.zdunczyk@fortis.pl',
        'w.sprynca@fortis.pl',
        'p.gwiazda@fortis.pl',
        'i.martyla@fortis.pl',
        'm.kotermanska@fortis.pl',
        'a.piotrowska@fortis.pl',
        'k.wardak@fortis.pl',
        'p.wronowski@fortis.pl',
        'j.wojno@fortis.pl',
        'n.grinberg@fortis.pl',
        'k.lajszczak@fortis.pl',
        'i.kadziewicz@fortis.pl',
        'j.sieradzan-wencka@fortis.pl',
        'a.guziuk@fortis.pl',
        'k.oleksinski@fortis.pl',
        'a.klinger@fortis.pl',
        'a.gaul@fortis.pl',
        'd.bilostotska@fortis.pl',
        'j.galazyn@fortis.pl',
        'k.lipinski@fortis.pl',
        'm.kozel@fortis.pl',
        'a.owczarek@fortis.pl',
        'a.pietraszko@fortis.pl',
        'p.szczepankiewicz@fortis.pl',
        'd.korobka@fortis.pl',
        'a.ciolak@fortis.pl',
        'w.mazur@fortis.pl',
        'j.zubielewicz@fortis.pl',
        'a.plonska@fortis.pl',
        'a.golas@fortis.pl',
        'm.gniewek@fortis.pl',
        'p.borys@fortis.pl',
        's.frackiewicz@fortis.pl',
        'k.sochacki@fortis.pl',
        'm.shykarava@fortis.pl',
        'v.shtoiko@fortis.pl',
        't.ciechanski@fortis.pl',
        'v.sychevska@fortis.pl',
        'j.rybnik@fortis.pl',
        'j.kosun@fortis.pl',
    ];

    public function run(): void
    {
        $now = now();

        foreach ($this->emails as $email) {
            $name = str_before($email, '@');

            DB::table('participants')->updateOrInsert(
                ['email' => $email],
                [
                    'name'       => $name,
                    'password'   => 'ustaw_se',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }
}
