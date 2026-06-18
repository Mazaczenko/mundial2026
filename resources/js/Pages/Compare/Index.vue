<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { teamCode } from '@/utils/teamCode';

interface ParticipantSummary {
    id: number;
    name: string;
    eliminated: boolean;
    points: number;
    position: number | null;
}

interface SimpleParticipant {
    id: number;
    name: string;
}

interface BetData {
    prediction_1x2: '1' | 'X' | '2';
    predicted_home: number | null;
    predicted_away: number | null;
    is_correct: boolean | null;
}

interface MatchRow {
    id: number;
    home_team: string;
    away_team: string;
    home_team_flag: string | null;
    away_team_flag: string | null;
    kickoff_at: string;
    stage: 'group' | 'r32' | 'r16' | 'qf' | 'sf' | 'final';
    group_name: string | null;
    score_home: number | null;
    score_away: number | null;
    result_type: 'FT' | 'AET' | 'PEN' | null;
    betA: BetData | null;
    betB: BetData | null;
}

interface Summary {
    a_correct: number;
    b_correct: number;
    a_wins: number;
    b_wins: number;
    draws: number;
    a_exact: number;
    b_exact: number;
}

interface Props {
    participantA: ParticipantSummary;
    participantB: ParticipantSummary;
    allParticipants: SimpleParticipant[];
    matches: MatchRow[];
    summary: Summary;
}

const props = defineProps<Props>();

const selectedA = ref(props.participantA.id);
const selectedB = ref(props.participantB.id);

const otherForA = computed(() => props.allParticipants.filter(p => p.id !== selectedA.value));
const otherForB = computed(() => props.allParticipants.filter(p => p.id !== selectedB.value));

function applyCompare() {
    if (selectedA.value === selectedB.value) return;
    router.get(route('compare.index'), { a: selectedA.value, b: selectedB.value });
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('pl-PL', {
        day: 'numeric',
        month: 'short',
        timeZone: 'Europe/Warsaw',
    });
}

function resultTypeSuffix(type: string | null): string {
    if (type === 'AET') return 'po dogryw.';
    if (type === 'PEN') return 'po kar.';
    return '';
}

function betCellClass(bet: BetData | null): string {
    if (!bet) return 'text-gray-300 dark:text-gray-600';
    if (bet.is_correct === true) return 'text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20';
    if (bet.is_correct === false) return 'text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-900/20';
    return 'text-gray-500 dark:text-gray-400';
}

const stageLabels: Record<string, string> = {
    group: 'Grupy',
    r32: '1/32',
    r16: '1/16',
    qf: 'Ćwierćf.',
    sf: 'Półf.',
    final: 'Finał',
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`${participantA.name} vs ${participantB.name}`" />

        <div class="py-6">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

                <!-- Header -->
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <div class="px-6 py-5">
                        <h1 class="mb-4 text-xl font-bold text-gray-900 dark:text-white">
                            Porównanie: {{ participantA.name }} vs {{ participantB.name }}
                        </h1>

                        <!-- Selectors -->
                        <div class="flex items-center gap-3">
                            <select
                                v-model="selectedA"
                                @change="applyCompare"
                                class="flex-1 rounded-md border border-gray-300 bg-white py-1.5 pl-3 pr-8 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            >
                                <option v-for="p in allParticipants" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <span class="shrink-0 text-sm font-bold text-gray-400">vs</span>
                            <select
                                v-model="selectedB"
                                @change="applyCompare"
                                class="flex-1 rounded-md border border-gray-300 bg-white py-1.5 pl-3 pr-8 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            >
                                <option v-for="p in allParticipants" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Summary cards -->
                <div class="mb-6 grid grid-cols-3 gap-3">
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800 text-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 truncate">{{ participantA.name }}</p>
                        <p class="mt-1 text-3xl font-bold text-green-600 dark:text-green-400">{{ summary.a_wins }}</p>
                        <p class="text-xs text-gray-400">tylko A trafił</p>
                        <p class="mt-1 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ participantA.points }} pkt</p>
                        <p v-if="participantA.position" class="text-xs text-gray-400">Miejsce {{ participantA.position }}</p>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800 text-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Remisy</p>
                        <p class="mt-1 text-3xl font-bold text-gray-500 dark:text-gray-400">{{ summary.draws }}</p>
                        <p class="text-xs text-gray-400">obaj trafili lub żaden</p>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800 text-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 truncate">{{ participantB.name }}</p>
                        <p class="mt-1 text-3xl font-bold text-blue-600 dark:text-blue-400">{{ summary.b_wins }}</p>
                        <p class="text-xs text-gray-400">tylko B trafił</p>
                        <p class="mt-1 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ participantB.points }} pkt</p>
                        <p v-if="participantB.position" class="text-xs text-gray-400">Miejsce {{ participantB.position }}</p>
                    </div>
                </div>

                <!-- Correct + exact summary -->
                <div class="mb-6 grid grid-cols-2 gap-3">
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Łączne trafienia</p>
                        <div class="mt-2 flex items-center justify-between">
                            <div class="text-center">
                                <p class="text-lg font-bold text-green-600 dark:text-green-400">{{ summary.a_correct }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ participantA.name }}</p>
                            </div>
                            <span class="text-gray-300 dark:text-gray-600">vs</span>
                            <div class="text-center">
                                <p class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ summary.b_correct }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ participantB.name }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Dokładne wyniki</p>
                        <div class="mt-2 flex items-center justify-between">
                            <div class="text-center">
                                <p class="text-lg font-bold text-green-600 dark:text-green-400">{{ summary.a_exact }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ participantA.name }}</p>
                            </div>
                            <span class="text-gray-300 dark:text-gray-600">vs</span>
                            <div class="text-center">
                                <p class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ summary.b_exact }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ participantB.name }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match table -->
                <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Mecz po meczu</h2>
                    </div>

                    <div v-if="matches.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                        Brak rozegranych meczów.
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-green-600 dark:text-green-400">
                                        {{ participantA.name }}
                                    </th>
                                    <th class="px-4 py-2 text-center text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                        Mecz
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-blue-600 dark:text-blue-400">
                                        {{ participantB.name }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr
                                    v-for="match in matches"
                                    :key="match.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/30"
                                >
                                    <!-- Bet A -->
                                    <td class="px-4 py-2.5 text-right">
                                        <div v-if="match.betA">
                                            <span
                                                class="inline-flex items-center gap-0.5 rounded px-2 py-0.5 text-sm font-bold"
                                                :class="betCellClass(match.betA)"
                                            >
                                                {{ match.betA.prediction_1x2 }}
                                                <span v-if="match.betA.is_correct === true">✓</span>
                                                <span v-else-if="match.betA.is_correct === false">✗</span>
                                            </span>
                                            <span
                                                v-if="match.betA.predicted_home !== null && match.betA.predicted_away !== null"
                                                class="ml-1 text-xs text-gray-400 dark:text-gray-500"
                                            >{{ match.betA.predicted_home }}:{{ match.betA.predicted_away }}</span>
                                        </div>
                                        <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                    </td>

                                    <!-- Match -->
                                    <td class="px-4 py-2.5 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <span v-if="match.home_team_flag && match.home_team_flag.length <= 4" class="text-sm leading-none">{{ match.home_team_flag }}</span>
                                            <img v-else-if="match.home_team_flag" :src="match.home_team_flag" class="h-3 w-4.5 object-contain" :alt="match.home_team" />
                                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ teamCode(match.home_team) }}</span>
                                            <span class="text-xs font-bold tabular-nums text-gray-900 dark:text-white">{{ match.score_home }}:{{ match.score_away }}</span>
                                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ teamCode(match.away_team) }}</span>
                                            <span v-if="match.away_team_flag && match.away_team_flag.length <= 4" class="text-sm leading-none">{{ match.away_team_flag }}</span>
                                            <img v-else-if="match.away_team_flag" :src="match.away_team_flag" class="h-3 w-4.5 object-contain" :alt="match.away_team" />
                                        </div>
                                        <div class="mt-0.5 text-xs text-gray-400">
                                            {{ stageLabels[match.stage] }} · {{ formatDate(match.kickoff_at) }}
                                            <span v-if="resultTypeSuffix(match.result_type)"> · {{ resultTypeSuffix(match.result_type) }}</span>
                                        </div>
                                    </td>

                                    <!-- Bet B -->
                                    <td class="px-4 py-2.5 text-left">
                                        <div v-if="match.betB">
                                            <span
                                                class="inline-flex items-center gap-0.5 rounded px-2 py-0.5 text-sm font-bold"
                                                :class="betCellClass(match.betB)"
                                            >
                                                {{ match.betB.prediction_1x2 }}
                                                <span v-if="match.betB.is_correct === true">✓</span>
                                                <span v-else-if="match.betB.is_correct === false">✗</span>
                                            </span>
                                            <span
                                                v-if="match.betB.predicted_home !== null && match.betB.predicted_away !== null"
                                                class="ml-1 text-xs text-gray-400 dark:text-gray-500"
                                            >{{ match.betB.predicted_home }}:{{ match.betB.predicted_away }}</span>
                                        </div>
                                        <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Back link -->
                <div class="mt-4">
                    <Link
                        :href="route('participants.show', participantA.id)"
                        class="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                    >← Wróć do profilu {{ participantA.name }}</Link>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
