<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    eventTitle: {
        type: String,
        default: 'Event',
    },
});
</script>

<template>
    <Head :title="`Event Suspended • ${eventTitle}`" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 flex flex-col items-center justify-center p-6 text-center">
        <div class="w-20 h-20 rounded-3xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-4xl mb-6 shadow-sm">
            ⏸️
        </div>

        <h1 class="text-2xl font-black text-slate-900 dark:text-white mb-2">Event Unavailable</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 max-w-md mb-6 leading-relaxed">
            This event page is temporarily suspended. Please contact the event organizer for further information or updates.
        </p>

        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-sm text-xs text-slate-500 shadow-sm">
            Event: {{ eventTitle }}
        </div>
    </div>
</template>
