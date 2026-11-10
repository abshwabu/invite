<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    guests: {
        type: Array,
        required: true,
    },
    totals: {
        type: Object,
        required: true,
    },
});

const groupedGuests = computed(() => {
    const groups = {};
    for (const g of props.guests) {
        const letter = g.letter || '#';
        if (!groups[letter]) {
            groups[letter] = [];
        }
        groups[letter].push(g);
    }
    return groups;
});

const printSheet = () => {
    window.print();
};
</script>

<template>
    <Head :title="`${event.title} • Day-of Door List`" />

    <div class="min-h-screen bg-slate-100 p-6 print:p-0 print:bg-white text-slate-900 font-sans">
        <!-- Control Bar -->
        <div class="max-w-4xl mx-auto mb-6 print:hidden flex items-center justify-between bg-white p-4 rounded-2xl shadow-sm border border-slate-200">
            <div>
                <h1 class="text-base font-bold text-slate-900">Day-of Entrance Door List</h1>
                <p class="text-xs text-slate-500">Alphabetical clipboard paper list for manual check-in at non-QR events.</p>
            </div>
            <button
                @click="printSheet"
                class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-xl shadow transition"
            >
                🖨️ Print Door List
            </button>
        </div>

        <!-- Paper Container -->
        <div class="max-w-4xl mx-auto bg-white p-8 rounded-3xl shadow-sm border border-slate-200 print:shadow-none print:border-none print:p-4">
            <!-- Header -->
            <div class="border-b-2 border-slate-900 pb-3 mb-6 flex items-start justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-widest text-slate-500">Day-of Entrance Door List</span>
                    <h1 class="text-2xl font-black text-slate-900 mt-0.5">{{ event.title }}</h1>
                    <p class="text-xs text-slate-600">
                        📅 {{ event.starts_at }}
                        <span v-if="event.ethiopian_date" class="ms-1 font-semibold">({{ event.ethiopian_date.formatted }})</span>
                        <span v-if="event.venue_name" class="ms-2">• 📍 {{ event.venue_name }}</span>
                    </p>
                </div>
                <div class="text-right text-xs font-mono">
                    <div>Guests: <strong>{{ totals.total }}</strong></div>
                    <div>Expected Heads: <strong>{{ totals.expected_headcount }}</strong></div>
                </div>
            </div>

            <!-- Alphabetical Sections -->
            <div class="space-y-6">
                <div v-for="(list, letter) in groupedGuests" :key="letter" class="break-inside-avoid">
                    <div class="flex items-center gap-2 border-b border-slate-300 pb-1 mb-2">
                        <span class="w-6 h-6 rounded bg-slate-900 text-white font-black text-xs flex items-center justify-center">
                            {{ letter }}
                        </span>
                        <span class="text-xs font-bold text-slate-600">{{ list.length }} guest(s)</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
                        <div
                            v-for="g in list"
                            :key="g.id"
                            class="flex items-center justify-between p-2 rounded border border-slate-200 text-xs break-inside-avoid"
                        >
                            <div class="flex items-center gap-2.5 truncate">
                                <!-- Checkbox -->
                                <div class="w-5 h-5 border-2 border-slate-800 rounded flex items-center justify-center shrink-0">
                                    <span v-if="g.checked_in" class="font-bold text-xs">✓</span>
                                </div>
                                <div class="truncate">
                                    <p class="font-bold text-slate-900 truncate">{{ g.name }}</p>
                                    <p class="text-[10px] text-slate-500 font-mono">{{ g.phone || 'No phone' }}</p>
                                </div>
                            </div>

                            <div class="text-right shrink-0 font-mono text-[11px] ms-2">
                                <span class="px-2 py-0.5 rounded bg-slate-100 font-bold">Party of {{ g.party_size }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 pt-4 border-t border-slate-200 text-center text-[10px] text-slate-400">
                Printed from Teri App • Sorted Alphabetically for Door Staff
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    @page {
        margin: 1cm;
        size: auto;
    }
}
</style>
