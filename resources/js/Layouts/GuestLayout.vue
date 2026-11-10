<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
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
            <Link href="/" class="flex flex-col items-center">
                <BrandLogo variant="stacked" class="h-20 w-auto" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-6 shadow-md sm:max-w-md sm:rounded-xl dark:bg-gray-800 border border-gray-100 dark:border-gray-700"
        >
            <slot />
        </div>
    </div>
</template>
