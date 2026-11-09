<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100"
                >
                    {{ $t('My Events') }}
                </h2>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('templates.index')"
                        class="text-xs font-semibold text-gray-700 dark:text-gray-300 hover:text-amber-600 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700"
                    >
                        {{ $t('Gallery') }}
                    </Link>
                    <Link
                        :href="route('editor.show')"
                        class="rounded-lg bg-amber-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-500"
                    >
                        + Create Invitation
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Events Grid -->
                <div v-if="events && events.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="evt in events"
                        :key="evt.id"
                        class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-medium px-2.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                    {{ evt.category?.name_en || 'Event' }}
                                </span>
                                <span class="text-[11px] uppercase tracking-wider font-semibold text-gray-400">
                                    {{ evt.status }}
                                </span>
                            </div>

                            <h3 class="font-bold text-lg text-gray-900 dark:text-white line-clamp-1">
                                {{ evt.title }}
                            </h3>

                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-1">
                                📍 {{ evt.venue_name || 'Venue TBA' }}
                            </p>

                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                🗓 {{ new Date(evt.starts_at).toLocaleDateString() }}
                            </p>
                        </div>

                        <div class="pt-6 mt-6 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <span class="text-xs text-gray-600 dark:text-gray-300 font-medium">
                                👥 {{ evt.guests_count || 0 }} Guests
                            </span>

                            <Link
                                :href="route('editor.show', { template: evt.template_id || undefined })"
                                class="text-xs font-semibold text-amber-600 hover:text-amber-500"
                            >
                                Edit Card →
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="rounded-3xl border-2 border-dashed border-gray-200 dark:border-gray-800 p-12 text-center bg-white dark:bg-gray-900 shadow-xs"
                >
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-950/50 text-3xl mb-4">
                        💌
                    </div>

                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $t('No events yet. Start by creating an invitation!') }}
                    </h3>

                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                        Pick a categorized Ethiopian template (Wedding, Birthday, Religious, Corporate) and personalize it.
                    </p>

                    <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                        <Link
                            :href="route('templates.index')"
                            class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500"
                        >
                            {{ $t('Gallery') }}
                        </Link>
                        <Link
                            :href="route('editor.show')"
                            class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            {{ $t('Start from Scratch') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
