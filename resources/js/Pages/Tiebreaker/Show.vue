<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { TiebreakerPick } from '@/types';

interface Props {
    pick: TiebreakerPick | null;
    deadline: string;
    allowed: boolean;
}

const props = defineProps<Props>();

const deadlinePassed = computed(() => new Date() >= new Date(props.deadline));
const canEdit = computed(() => props.allowed && !deadlinePassed.value);

const form = useForm({
    top_scorer_name: props.pick?.top_scorer_name ?? '',
});

const submit = () => {
    form.post(route('tiebreaker.store'));
};

function formatDeadline(iso: string): string {
    return new Date(iso).toLocaleString('pl-PL', {
        dateStyle: 'long',
        timeStyle: 'short',
        timeZone: 'Europe/Warsaw',
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Tiebreaker" />

        <div class="py-6">
            <div class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                        🥇 Tiebreaker — Król strzelców
                    </h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        W przypadku równej liczby punktów, kto bezbłędnie wskazał króla strzelców turnieju — wygrywa.
                        Twój typ jest <strong>tajny</strong> — nikt go nie widzi przed końcem turnieju.
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                        Deadline: {{ formatDeadline(deadline) }}
                    </p>

                    <!-- Brak dostępu -->
                    <div v-if="!allowed" class="mt-6 rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
                        Nie masz dostępu do tiebreakerów.
                    </div>

                    <!-- Dostęp OK -->
                    <template v-else>
                        <div v-if="pick && deadlinePassed" class="mt-6 rounded-md bg-gray-50 p-4 dark:bg-gray-900">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Twój typ (zablokowany po starcie turnieju):</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ pick.top_scorer_name }}</p>
                        </div>

                        <form v-else-if="canEdit" @submit.prevent="submit" class="mt-6">
                            <div>
                                <label for="scorer" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Imię i nazwisko króla strzelców
                                </label>
                                <input
                                    id="scorer"
                                    type="text"
                                    v-model="form.top_scorer_name"
                                    placeholder="np. Erling Haaland"
                                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    required
                                />
                                <p v-if="form.errors.top_scorer_name" class="mt-1 text-xs text-red-500">
                                    {{ form.errors.top_scorer_name }}
                                </p>
                            </div>

                            <div v-if="pick" class="mt-2 rounded-md bg-blue-50 p-3 text-xs text-blue-700 dark:bg-blue-900/20 dark:text-blue-300">
                                Aktualny typ: <strong>{{ pick.top_scorer_name }}</strong>. Możesz zmienić do deadlinu.
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="mt-4 w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ pick ? 'Zmień typ' : 'Zapisz typ' }}
                            </button>
                        </form>

                        <div v-else class="mt-6 rounded-md bg-yellow-50 p-4 text-sm text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-300">
                            Deadline minął. Nie zapisałeś swojego typu króla strzelców.
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
