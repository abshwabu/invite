<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col items-center bg-gray-50 pt-6 sm:justify-center sm:pt-0 dark:bg-gray-900"
    >
        <div class="absolute top-4 right-4">
            <LanguageSwitcher />
        </div>

        <div>
            <Link href="/" class="flex flex-col items-center gap-2">
                <ApplicationLogo class="h-16 w-16 fill-current text-amber-600" />
                <span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Invite</span>
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-6 shadow-md sm:max-w-md sm:rounded-xl dark:bg-gray-800 border border-gray-100 dark:border-gray-700"
        >
            <slot />
        </div>
    </div>
</template>
