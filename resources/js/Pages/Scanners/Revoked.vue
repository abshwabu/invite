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
});
</script>

<template>
    <Head :title="`Scanner Revoked • ${scannerName}`" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col items-center justify-center p-6 text-center select-none">
        <div class="w-20 h-20 rounded-full bg-red-500/20 border-2 border-red-500/50 flex items-center justify-center text-4xl mb-6 shadow-xl shadow-red-500/20">
            🚫
        </div>

        <h1 class="text-2xl font-bold text-white mb-2">Scanner Access Revoked</h1>
        <p class="text-sm text-slate-400 max-w-sm mb-2">
            The event host has revoked admission access for <strong class="text-slate-200">{{ scannerName }}</strong>.
        </p>
        <p class="text-xs text-slate-500 mb-6">
            Event: {{ eventTitle }}
        </p>

        <div class="p-4 bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-xs text-xs text-slate-400">
            This scanner cannot scan tickets or check in guests. Please contact the event organizer for an updated link.
        </div>
    </div>
</template>
