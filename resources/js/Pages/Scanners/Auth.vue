<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: {
        type: String,
        required: true,
    },
    scannerName: {
        type: String,
        required: true,
    },
    eventTitle: {
        type: String,
        required: true,
    },
    failedAttempts: {
        type: Number,
        default: 0,
    },
});

const form = useForm({
    pin: '',
});

const appendDigit = (num) => {
    if (form.pin.length < 6) {
        form.pin += num;
    }
};

const backspace = () => {
    if (form.pin.length > 0) {
        form.pin = form.pin.slice(0, -1);
    }
};

const clearPin = () => {
    form.pin = '';
};

const submitPin = () => {
    if (form.pin.length < 4) return;
    form.post(route('scanner.auth', { token: props.token }), {
        onError: () => {
            form.pin = '';
        },
    });
};
</script>

<template>
    <Head :title="`Door Check-in • ${scannerName}`" />

    <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col justify-between p-4 sm:p-6 select-none max-w-md mx-auto">
        <!-- Top branding & Event info -->
        <div class="text-center pt-6 space-y-1">
            <div class="inline-flex items-center space-x-1.5 px-3 py-1 bg-purple-500/20 text-purple-300 text-xs font-semibold rounded-full border border-purple-500/30">
                <span>📷</span>
                <span>Door Scanner</span>
            </div>
            <h1 class="text-xl font-bold text-white mt-2">{{ scannerName }}</h1>
            <p class="text-xs text-slate-400">{{ eventTitle }}</p>
        </div>

        <!-- PIN Display & Dots -->
        <div class="text-center py-6">
            <p class="text-xs text-slate-400 mb-4">Enter your 4-6 digit security PIN</p>

            <div class="flex items-center justify-center space-x-3 mb-4">
                <div
                    v-for="i in 6"
                    :key="i"
                    class="w-4 h-4 rounded-full border-2 transition-all duration-150"
                    :class="form.pin.length >= i ? 'bg-purple-500 border-purple-400 scale-110 shadow-lg shadow-purple-500/50' : 'border-slate-600 bg-slate-800'"
                ></div>
            </div>

            <!-- Error Feedback -->
            <div v-if="form.errors.pin" class="p-3 bg-red-500/20 border border-red-500/40 rounded-xl text-red-200 text-xs font-medium max-w-xs mx-auto animate-shake">
                {{ form.errors.pin }}
            </div>
            <div v-else-if="failedAttempts > 0" class="text-amber-400 text-xs">
                {{ 5 - failedAttempts }} attempt(s) remaining before lockout.
            </div>
        </div>

        <!-- Numeric Keypad (Touch friendly, large buttons) -->
        <div class="w-full max-w-xs mx-auto pb-6 space-y-3">
            <div class="grid grid-cols-3 gap-3">
                <button
                    v-for="digit in [1, 2, 3, 4, 5, 6, 7, 8, 9]"
                    :key="digit"
                    type="button"
                    @click="appendDigit(digit)"
                    class="h-16 rounded-2xl bg-slate-800 hover:bg-slate-700 active:bg-purple-600 text-2xl font-bold text-white shadow-md transition-all active:scale-95 flex items-center justify-center"
                >
                    {{ digit }}
                </button>

                <button
                    type="button"
                    @click="clearPin"
                    class="h-16 rounded-2xl bg-slate-800/60 hover:bg-slate-700 text-sm font-semibold text-slate-400 transition active:scale-95 flex items-center justify-center"
                >
                    Clear
                </button>

                <button
                    type="button"
                    @click="appendDigit(0)"
                    class="h-16 rounded-2xl bg-slate-800 hover:bg-slate-700 active:bg-purple-600 text-2xl font-bold text-white shadow-md transition-all active:scale-95 flex items-center justify-center"
                >
                    0
                </button>

                <button
                    type="button"
                    @click="backspace"
                    class="h-16 rounded-2xl bg-slate-800/60 hover:bg-slate-700 text-xl font-bold text-slate-300 transition active:scale-95 flex items-center justify-center"
                >
                    ⌫
                </button>
            </div>

            <!-- Unlock / Submit Button -->
            <button
                type="button"
                @click="submitPin"
                :disabled="form.pin.length < 4 || form.processing"
                class="w-full py-4 rounded-2xl font-bold text-base transition-all shadow-lg"
                :class="form.pin.length >= 4 && !form.processing ? 'bg-purple-600 hover:bg-purple-500 text-white shadow-purple-600/30' : 'bg-slate-800 text-slate-500 cursor-not-allowed'"
            >
                <span v-if="form.processing">Verifying...</span>
                <span v-else>Unlock Scanner →</span>
            </button>
        </div>

        <!-- Footer -->
        <div class="text-center text-[10px] text-slate-500 pb-2">
            Secure single-use check-in system • Teri
        </div>
    </div>
</template>

<style scoped>
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-6px); }
    40%, 80% { transform: translateX(6px); }
}
.animate-shake {
    animation: shake 0.3s ease-in-out;
}
</style>
