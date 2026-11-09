<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    scannerName: {
        type: String,
        required: true,
    },
    lockedUntil: {
        type: String,
        required: true,
    },
    remainingMinutes: {
        type: Number,
        default: 15,
    },
});

const countdown = ref(props.remainingMinutes * 60);
let timer = null;

onMounted(() => {
    timer = setInterval(() => {
        if (countdown.value > 0) {
            countdown.value--;
        } else {
            window.location.reload();
        }
    }, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

const formatTime = (secs) => {
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    return `${m}:${s < 10 ? '0' : ''}${s}`;
};
</script>

<template>
    <Head :title="`Scanner Locked • ${scannerName}`" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col items-center justify-center p-6 text-center select-none">
        <div class="w-20 h-20 rounded-full bg-red-500/20 border-2 border-red-500/50 flex items-center justify-center text-4xl mb-6 shadow-xl shadow-red-500/20">
            🔒
        </div>

        <h1 class="text-2xl font-bold text-white mb-2">Scanner Temporarily Locked</h1>
        <p class="text-sm text-slate-400 max-w-sm mb-6">
            Too many consecutive incorrect PIN attempts were detected for <strong class="text-slate-200">{{ scannerName }}</strong>.
        </p>

        <div class="p-4 bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-xs mb-6 shadow-inner">
            <div class="text-xs text-slate-500 uppercase tracking-wider font-semibold mb-1">Time Remaining</div>
            <div class="text-3xl font-mono font-bold text-red-400">{{ formatTime(countdown) }}</div>
        </div>

        <p class="text-xs text-slate-500 max-w-xs">
            The scanner will automatically unlock once the security cooldown expires. Contact the event host if you need immediate assistance.
        </p>

        <button
            type="button"
            @click="() => window.location.reload()"
            class="mt-8 px-6 py-2.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold rounded-xl text-slate-300 transition"
        >
            Refresh Status
        </button>
    </div>
</template>
