<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { MatchData } from '@/types';

interface Props {
    matchesByDate: Record<string, MatchData[]>;
    participant: {
        id: number;
        name: string;
        eliminated: boolean;
        paid_entry: boolean;
    };
}

const props = defineProps<Props>();

const stageLabels: Record<string, string> = {
    group: 'Faza grupowa',
    r32: '1/32 finału',
    r16: '1/16 finału',
    qf: 'Ćwierćfinał',
    sf: 'Półfinał',
    final: 'Finał',
};

function optionLabel(opt: string, match: MatchData): string {
    if (opt === '1') return match.home_team;
    if (opt === '2') return match.away_team;
    return 'Remis';
}

import { teamCode } from '@/utils/teamCode';

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('pl-PL', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        timeZone: 'Europe/Warsaw',
    });
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('pl-PL', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Europe/Warsaw',
    });
}

const betForms = ref<Record<number, ReturnType<typeof useForm>>>({});

function getOrCreateForm(match: MatchData) {
    if (!betForms.value[match.id]) {
        betForms.value[match.id] = useForm({
            match_id: match.id,
            prediction_1x2: match.my_bet?.prediction_1x2 ?? '',
            predicted_home: match.my_bet?.predicted_home ?? null,
            predicted_away: match.my_bet?.predicted_away ?? null,
        });
    }
    return betForms.value[match.id];
}

function submitBet(match: MatchData) {
    const form = getOrCreateForm(match);
    if (match.my_bet) {
        form.put(route('bets.update', match.my_bet.id));
    } else {
        form.post(route('bets.store'));
    }
}

function isKnockout(stage: string): boolean {
    return stage !== 'group';
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Typy — Mundial 2026" />

        <div class="py-6">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

<div v-if="participant.eliminated" class="mb-4 rounded-md bg-orange-50 border border-orange-200 p-4 text-sm text-orange-800 dark:bg-orange-900/20 dark:text-orange-300">
                    Zostałeś wyeliminowany z oficjalnego rankingu (3 nieoobstawione mecze). Możesz nadal typować dla zabawy.
                </div>

                <div v-for="(matches, date) in matchesByDate" :key="date" class="mb-8">
                    <h2 class="mb-3 text-lg font-semibold capitalize text-gray-700 dark:text-gray-300">
                        {{ formatDate(matches[0].kickoff_at) }}
                    </h2>

                    <div class="space-y-4">
                        <div
                            v-for="match in matches"
                            :key="match.id"
                            class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800"
                        >
                            <!-- Match header -->
                            <div class="p-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                        {{ stageLabels[match.stage] }}
                                        <span v-if="match.group_name"> · Gr. {{ match.group_name }}</span>
                                    </span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatTime(match.kickoff_at) }}
                                    </span>
                                </div>

                                <div class="mt-3 grid items-center gap-2" style="grid-template-columns: 1fr 5rem 1fr">
                                    <!-- Home team -->
                                    <div class="flex min-w-0 flex-col items-center text-center">
                                        <span class="text-3xl" v-if="match.home_team_flag && match.home_team_flag.length <= 4">{{ match.home_team_flag }}</span>
                                        <img v-else-if="match.home_team_flag" :src="match.home_team_flag" class="h-8 w-12 object-contain" :alt="match.home_team" />
                                        <abbr class="mt-1 cursor-default text-sm font-bold tracking-wide text-gray-900 no-underline dark:text-white" :title="match.home_team">{{ teamCode(match.home_team) }}</abbr>
                                    </div>

                                    <!-- Score / VS -->
                                    <div class="text-center">
                                        <template v-if="match.status === 'finished'">
                                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                                {{ match.score_home }} : {{ match.score_away }}
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500">Wynik końcowy</div>
                                        </template>
                                        <template v-else>
                                            <div class="text-xl font-light text-gray-400">vs</div>
                                            <div v-if="!match.can_bet" class="mt-1 text-xs text-red-500">Deadline minął</div>
                                        </template>
                                    </div>

                                    <!-- Away team -->
                                    <div class="flex min-w-0 flex-col items-center text-center">
                                        <span class="text-3xl" v-if="match.away_team_flag && match.away_team_flag.length <= 4">{{ match.away_team_flag }}</span>
                                        <img v-else-if="match.away_team_flag" :src="match.away_team_flag" class="h-8 w-12 object-contain" :alt="match.away_team" />
                                        <abbr class="mt-1 cursor-default text-sm font-bold tracking-wide text-gray-900 no-underline dark:text-white" :title="match.away_team">{{ teamCode(match.away_team) }}</abbr>
                                    </div>
                                </div>
                            </div>

                            <!-- Bet form (can still bet) -->
                            <div v-if="match.can_bet" class="border-t border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                <form @submit.prevent="submitBet(match)">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Twój typ:</span>
                                        <div class="flex gap-2">
                                            <label
                                                v-for="opt in ['1', 'X', '2']"
                                                :key="opt"
                                                class="flex cursor-pointer items-center gap-1.5"
                                            >
                                                <input
                                                    type="radio"
                                                    :name="'prediction_' + match.id"
                                                    :value="opt"
                                                    v-model="getOrCreateForm(match).prediction_1x2"
                                                    class="text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ opt }}</span>
                                                <abbr class="cursor-default text-xs text-gray-500 no-underline" :title="optionLabel(opt, match)">
                                                    {{ opt === 'X' ? 'Remis' : teamCode(optionLabel(opt, match)) }}
                                                </abbr>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Exact score (knockout only) -->
                                    <div v-if="isKnockout(match.stage)" class="mt-3 flex items-center gap-2">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Dokładny wynik:</span>
                                        <input
                                            type="number"
                                            min="0"
                                            max="20"
                                            v-model.number="getOrCreateForm(match).predicted_home"
                                            class="w-14 rounded border border-gray-300 px-2 py-1 text-center text-sm focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                            placeholder="–"
                                        />
                                        <span class="text-gray-500">:</span>
                                        <input
                                            type="number"
                                            min="0"
                                            max="20"
                                            v-model.number="getOrCreateForm(match).predicted_away"
                                            class="w-14 rounded border border-gray-300 px-2 py-1 text-center text-sm focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                            placeholder="–"
                                        />
                                        <span class="text-xs text-gray-400">(tiebreaker)</span>
                                    </div>

                                    <div class="mt-3">
                                        <button
                                            type="submit"
                                            :disabled="!getOrCreateForm(match).prediction_1x2 || getOrCreateForm(match).processing"
                                            class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            {{ match.my_bet ? 'Aktualizuj' : 'Zapisz typ' }}
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Result display (after deadline or finished) -->
                            <div v-else class="border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Twój typ: </span>
                                        <template v-if="match.my_bet">
                                            <span
                                                class="font-semibold"
                                                :class="{
                                                    'text-green-600': match.my_bet.is_correct === true,
                                                    'text-red-500': match.my_bet.is_correct === false,
                                                    'text-gray-800 dark:text-gray-200': match.my_bet.is_correct === null,
                                                }"
                                            >
                                                {{ match.my_bet.prediction_1x2 }}
                                                <span v-if="match.my_bet.is_correct === true"> ✓</span>
                                                <span v-else-if="match.my_bet.is_correct === false"> ✗</span>
                                            </span>
                                        </template>
                                        <span v-else class="text-gray-400">–</span>
                                    </div>
                                </div>

                                <!-- Others' bets (after deadline) -->
                                <div v-if="match.others_bets.length > 0" class="mt-2">
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="ob in match.others_bets"
                                            :key="ob.participant_name"
                                            class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                        >
                                            {{ ob.participant_name }}: <strong>{{ ob.prediction_1x2 }}</strong>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="Object.keys(matchesByDate).length === 0" class="rounded-lg bg-white p-8 text-center text-gray-500 shadow dark:bg-gray-800">
                    Brak meczów do wyświetlenia. Admin musi zaimportować terminarz (<code>mundial:import-fixtures</code>).
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
