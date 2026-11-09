<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    scannerName: {
        type: String,
        required: true,
    },
    eventTitle: {
        type: String,
        required: true,
    },
    eventStatus: {
        type: String,
        default: 'closed',
    },
});
</script>

<template>
    <Head :title="`Event Closed • ${scannerName}`" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col items-center justify-center p-6 text-center select-none">
        <div class="w-20 h-20 rounded-full bg-amber-500/20 border-2 border-amber-500/50 flex items-center justify-center text-4xl mb-6 shadow-xl shadow-amber-500/20">
            🔒
        </div>

        <h1 class="text-2xl font-bold text-white mb-2">Event Closed</h1>
        <p class="text-sm text-slate-400 max-w-sm mb-2">
            Admission scanning is disabled because <strong class="text-slate-200">{{ eventTitle }}</strong> has ended or is closed.
        </p>
        <p class="text-xs text-slate-500 mb-6">
            Scanner: {{ scannerName }}
        </p>

        <div class="p-4 bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-xs text-xs text-slate-400">
            No further check-ins can be recorded for this event.
        </div>
    </div>
</template>
