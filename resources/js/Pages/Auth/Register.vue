<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TurnstileWidget from '@/Components/TurnstileWidget.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const localDraft = ref(null);

onMounted(() => {
    try {
        const stored = localStorage.getItem('invite_guest_draft');
        if (stored) {
            localDraft.value = JSON.parse(stored);
        }
    } catch (e) {
        // ignore JSON parse error
    }
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    'cf-turnstile-response': '',
    draft: null,
    tier: typeof window !== 'undefined' ? (new URLSearchParams(window.location.search)).get('tier') || '' : '',
});

const submit = () => {
    form.draft = localDraft.value;
    form.post(route('register'), {
        onSuccess: () => {
            if (localDraft.value) {
                localStorage.removeItem('invite_guest_draft');
            }
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Register')" />

        <div v-if="localDraft" class="mb-4 rounded-lg bg-amber-50 dark:bg-amber-950/40 p-3 text-xs text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800">
            <strong>{{ $t('Guest Mode') }}:</strong> Your card draft will be automatically saved to your account upon signing up!
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="name" :value="$t('Profile Information')" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    placeholder="Abebe Kebede"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" :value="$t('Email')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    placeholder="abebe@example.com"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="phone" :value="$t('Phone Number')" />

                <TextInput
                    id="phone"
                    type="tel"
                    class="mt-1 block w-full"
                    v-model="form.phone"
                    placeholder="0911223344 or +251911223344"
                    autocomplete="tel"
                />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Optional. Ethiopian mobile (+2519... / +2517...) for SMS cards
                </p>

                <InputError class="mt-2" :message="form.errors.phone" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" :value="$t('Password')" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    :value="$t('Confirm Password')"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <TurnstileWidget v-model="form['cf-turnstile-response']" class="mt-4" />
            <InputError class="mt-2" :message="form.errors['cf-turnstile-response']" />

            <div class="mt-6 flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="rounded-md text-xs text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-white"
                >
                    Already have an account? {{ $t('Log in') }}
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('Register') }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
