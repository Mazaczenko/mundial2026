<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Match header --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-6 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-950 dark:text-white">
                        {{ $this->record->home_team }} vs {{ $this->record->away_team }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $this->record->kickoff_at->setTimezone('Europe/Warsaw')->format('d.m.Y H:i') }}
                        &nbsp;&middot;&nbsp;
                        @php
                            $stageLabels = [
                                'group' => 'Faza grupowa',
                                'r32' => '1/32',
                                'r16' => '1/16',
                                'qf' => 'Ćwierćfinał',
                                'sf' => 'Półfinał',
                                'final' => 'Finał',
                            ];
                        @endphp
                        {{ $stageLabels[$this->record->stage] ?? $this->record->stage }}
                        @if($this->record->group_name)
                            &nbsp;&middot;&nbsp; Grupa {{ $this->record->group_name }}
                        @endif
                    </p>
                </div>
                @if($this->record->status === 'finished')
                    <div class="text-center">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Wynik końcowy</p>
                        <p class="text-3xl font-bold text-gray-950 dark:text-white">
                            {{ $this->record->score_home }}:{{ $this->record->score_away }}
                        </p>
                    </div>
                @else
                    <span class="inline-flex items-center rounded-full bg-yellow-50 px-3 py-1 text-xs font-medium text-yellow-700 ring-1 ring-inset ring-yellow-600/20">
                        Zaplanowany
                    </span>
                @endif
            </div>
        </div>

        {{-- Bets table --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 overflow-hidden dark:bg-gray-900 dark:ring-white/10">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-white/10">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Typy uczestników
                    <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        {{ $this->record->bets->count() }}
                    </span>
                </h3>
            </div>
            @if($this->record->bets->isEmpty())
                <div class="px-6 py-8 text-center text-sm text-gray-500">
                    Brak typów dla tego meczu.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Uczestnik</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">1X2</th>
                                @if($this->record->stage !== 'group')
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Dokładny wynik</th>
                                @endif
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Wynik</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Wpłata</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($this->record->bets->sortBy('participant.name') as $bet)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                        {{ $bet->participant->name }}
                                        @if($bet->participant->eliminated)
                                            <span class="ml-1 text-xs text-red-500">(wyeliminowany)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full font-bold text-sm
                                            @if($bet->prediction_1x2 === '1') bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300
                                            @elseif($bet->prediction_1x2 === 'X') bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300
                                            @else bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300
                                            @endif">
                                            {{ $bet->prediction_1x2 }}
                                        </span>
                                    </td>
                                    @if($this->record->stage !== 'group')
                                        <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">
                                            {{ $bet->predicted_home !== null ? "{$bet->predicted_home}:{$bet->predicted_away}" : '–' }}
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 text-center text-lg">
                                        @if($bet->is_correct === true) ✅
                                        @elseif($bet->is_correct === false) ❌
                                        @else <span class="text-gray-400 text-sm">–</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center text-lg">
                                        {{ $bet->participant->paid_entry ? '💰' : '' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Did not bet --}}
        @php
            $bettedIds = $this->record->bets->pluck('participant_id');
            $notBetted = \App\Models\Participant::whereNotIn('id', $bettedIds)->orderBy('name')->get();
        @endphp
        @if($notBetted->isNotEmpty())
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-6 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                    Nie obstawili
                    <span class="ml-2 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-600 dark:bg-red-900/50 dark:text-red-300">
                        {{ $notBetted->count() }}
                    </span>
                </h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($notBetted as $p)
                        <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs
                            {{ $p->eliminated ? 'bg-red-50 text-red-600 ring-1 ring-red-200 dark:bg-red-900/30 dark:text-red-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                            @if($p->eliminated) 🚫 @endif
                            {{ $p->name }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
