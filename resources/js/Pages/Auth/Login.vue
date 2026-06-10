<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    pin: '',
});

const submit = () => {
    form.post(route('login.post'), {
        onFinish: () => {
            form.reset('pin');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Logowanie — Mundial 2026" />

        <div class="mb-6 text-center">
            <span class="text-4xl">🏆</span>
            <h1 class="mt-2 text-xl font-bold text-gray-800 dark:text-gray-200">
                Mundial 2026
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Zaloguj się do typowania
            </p>
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Imię" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Twoje imię"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="pin" value="PIN" />

                <TextInput
                    id="pin"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.pin"
                    required
                    autocomplete="current-password"
                    placeholder="4-6 cyfr"
                    inputmode="numeric"
                />

                <InputError class="mt-2" :message="form.errors.pin" />
            </div>

            <div class="mt-6">
                <PrimaryButton
                    class="w-full justify-center"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Zaloguj się
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
