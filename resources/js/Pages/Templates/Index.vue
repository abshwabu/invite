<script setup>
import { Head, Link } from '@inertiajs/vue3';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { ref } from 'vue';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
});

const selectedCategory = ref('all');
</script>

<template>
    <Head :title="$t('Template Gallery')" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <!-- Top Nav -->
        <header class="border-b border-gray-200 dark:border-gray-800 bg-white/80 dark:bg-gray-900/80 backdrop-blur sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2">
                    <ApplicationLogo class="h-8 w-8 text-amber-600 fill-current" />
                    <span class="font-bold text-xl tracking-tight">Invite</span>
                </Link>

                <div class="flex items-center gap-3">
                    <LanguageSwitcher />

                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="route('dashboard')"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200 hover:text-amber-600 px-3 py-1.5"
                        >
                            {{ $t('Dashboard') }}
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200 hover:text-amber-600 px-3 py-1.5"
                        >
                            {{ $t('Log in') }}
                        </Link>
                        <Link
                            :href="route('register')"
                            class="rounded-lg bg-amber-600 px-3.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-500"
                        >
                            {{ $t('Register') }}
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
                        {{ $t('Template Gallery') }}
                    </h1>
                    <p class="mt-2 text-base text-gray-600 dark:text-gray-400">
                        {{ $t('Choose a category and start creating your custom card') }}
                    </p>
                </div>

                <Link
                    :href="route('editor.show')"
                    class="inline-flex items-center justify-center rounded-lg border border-amber-600 px-4 py-2 text-sm font-medium text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/30 transition-colors shadow-sm"
                >
                    + {{ $t('Start from Scratch') }}
                </Link>
            </div>

            <!-- Category Filter Tabs -->
            <div class="flex items-center gap-2 overflow-x-auto pb-4 mb-6 scrollbar-none">
                <button
                    type="button"
                    @click="selectedCategory = 'all'"
                    :class="[
                        'px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-colors',
                        selectedCategory === 'all'
                            ? 'bg-amber-600 text-white shadow-sm'
                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-gray-300'
                    ]"
                >
                    All Categories
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    type="button"
                    @click="selectedCategory = cat.slug"
                    :class="[
                        'px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-colors',
                        selectedCategory === cat.slug
                            ? 'bg-amber-600 text-white shadow-sm'
                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-gray-300'
                    ]"
                >
                    {{ $page.props.locale === 'am' ? cat.name_am : cat.name_en }}
                </button>
            </div>

            <!-- Template Grid -->
            <div class="space-y-12">
                <template v-for="cat in categories" :key="cat.id">
                    <section
                        v-if="(selectedCategory === 'all' || selectedCategory === cat.slug) && cat.templates?.length > 0"
                        class="space-y-4"
                    >
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>{{ $page.props.locale === 'am' ? cat.name_am : cat.name_en }}</span>
                            <span class="text-xs font-medium text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">
                                {{ cat.templates.length }}
                            </span>
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div
                                v-for="tmpl in cat.templates"
                                :key="tmpl.id"
                                class="group relative rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col"
                            >
                                <!-- Card Preview Banner -->
                                <div
                                    class="h-64 p-6 flex flex-col justify-center items-center text-center relative overflow-hidden transition-transform group-hover:scale-102"
                                    :style="{
                                        backgroundColor: tmpl.design_json?.backgroundColor || '#1f2937',
                                        color: tmpl.design_json?.textColor || '#ffffff'
                                    }"
                                >
                                    <div
                                        class="absolute inset-0 opacity-10 bg-radial from-white to-transparent"
                                    ></div>
                                    <span class="text-xs tracking-widest uppercase font-semibold text-amber-400 mb-1">
                                        {{ tmpl.design_json?.subheading || 'Special Invitation' }}
                                    </span>
                                    <h3 class="text-2xl font-serif font-bold text-balance">
                                        {{ tmpl.design_json?.heading || tmpl.name }}
                                    </h3>
                                    <p class="mt-2 text-sm opacity-90 font-light">
                                        {{ tmpl.design_json?.hosts || 'Honored Guests' }}
                                    </p>
                                    <p class="mt-3 text-xs opacity-75">
                                        {{ tmpl.design_json?.venue || 'Sheraton Addis' }}
                                    </p>
                                </div>

                                <!-- Card Details -->
                                <div class="p-4 flex items-center justify-between mt-auto bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700">
                                    <div>
                                        <h4 class="font-semibold text-sm text-gray-900 dark:text-gray-100">
                                            {{ tmpl.name }}
                                        </h4>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 capitalize">
                                            {{ tmpl.kind }}
                                        </span>
                                    </div>

                                    <Link
                                        :href="route('editor.show', { template: tmpl.id })"
                                        class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-500 transition-colors"
                                    >
                                        {{ $t('Use Template') }}
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </section>
                </template>
            </div>
        </main>
    </div>
</template>
