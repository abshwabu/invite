<script setup>
import { Head, Link } from '@inertiajs/vue3';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    featuredTemplates: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Invite - Create & Send Ethiopian Invitation Cards" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 flex flex-col justify-between">
        <!-- Top Nav -->
        <header class="border-b border-gray-200 dark:border-gray-800 bg-white/80 dark:bg-gray-900/80 backdrop-blur sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2">
                    <ApplicationLogo class="h-8 w-8 text-amber-600 fill-current" />
                    <span class="font-bold text-xl tracking-tight">Invite</span>
                </Link>

                <div class="flex items-center gap-4">
                    <Link
                        :href="route('templates.index')"
                        class="hidden sm:inline text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-amber-600"
                    >
                        {{ $t('Gallery') }}
                    </Link>

                    <Link
                        :href="route('editor.show')"
                        class="hidden sm:inline text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-amber-600"
                    >
                        {{ $t('Editor') }}
                    </Link>

                    <LanguageSwitcher />

                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="route('dashboard')"
                            class="rounded-lg bg-amber-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-500"
                        >
                            {{ $t('Dashboard') }}
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-xs font-semibold text-gray-700 dark:text-gray-300 hover:text-amber-600"
                        >
                            {{ $t('Log in') }}
                        </Link>
                        <Link
                            :href="route('register')"
                            class="rounded-lg bg-amber-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-500"
                        >
                            {{ $t('Register') }}
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="flex-1">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24 text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/60 px-3 py-1 text-xs font-semibold text-amber-800 dark:text-amber-300 mb-6">
                    <span>✨ Single-use QR Pass & SMS Delivery for Ethiopia</span>
                </div>

                <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-gray-900 dark:text-white max-w-4xl mx-auto leading-tight">
                    Create & Send Stunning Invitation Cards with <span class="text-amber-600">Door Check-In</span>
                </h1>

                <p class="mt-6 text-lg sm:text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                    Choose from authentic Ethiopian templates (Wedding, Memorial, Birthday, Graduation, Eid), customize online, add guests, and send invitations with single-use QR codes.
                </p>

                <!-- CTA Buttons -->
                <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center items-center">
                    <Link
                        :href="route('editor.show')"
                        class="w-full sm:w-auto rounded-xl bg-amber-600 px-6 py-3.5 text-sm font-bold text-white shadow-md hover:bg-amber-500 transition-colors"
                    >
                        Try Guest Mode Editor →
                    </Link>
                    <Link
                        :href="route('templates.index')"
                        class="w-full sm:w-auto rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-6 py-3.5 text-sm font-semibold text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-xs"
                    >
                        {{ $t('Template Gallery') }}
                    </Link>
                </div>
            </div>

            <!-- Categories Banner -->
            <div class="border-y border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-10">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <h2 class="text-center text-xs font-semibold uppercase tracking-wider text-gray-500 mb-6">
                        Templates for Every Special Ethiopian Occasion
                    </h2>

                    <div class="flex flex-wrap justify-center gap-3">
                        <Link
                            v-for="cat in categories"
                            :key="cat.id"
                            :href="route('templates.index')"
                            class="px-4 py-2 rounded-xl text-xs font-semibold bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:border-amber-500 hover:text-amber-600 transition-colors"
                        >
                            {{ $page.props.locale === 'am' ? cat.name_am : cat.name_en }}
                        </Link>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-gray-200 dark:border-gray-800 py-6 text-center text-xs text-gray-500">
            © 2027 Invite App. Built for celebrations in Ethiopia and beyond.
        </footer>
    </div>
</template>
