<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
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

const printSheet = () => {
    window.print();
};
</script>

<template>
    <Head :title="`${event.title} • Attendance Sheet`" />

    <div class="min-h-screen bg-slate-100 p-6 print:p-0 print:bg-white text-slate-900 font-sans">
        <!-- Print Trigger Bar (Hidden on print) -->
        <div class="max-w-5xl mx-auto mb-6 print:hidden flex items-center justify-between bg-white p-4 rounded-2xl shadow-sm border border-slate-200">
            <div>
                <h1 class="text-base font-bold text-slate-900">Printable Attendance Sheet</h1>
                <p class="text-xs text-slate-500">Formatted for standard A4 / Letter paper. Press Print or Ctrl+P.</p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    @click="printSheet"
                    class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-xl shadow transition"
                >
                    🖨️ Print Sheet
                </button>
            </div>
        </div>

        <!-- Sheet Container -->
        <div class="max-w-5xl mx-auto bg-white p-8 rounded-3xl shadow-sm border border-slate-200 print:shadow-none print:border-none print:p-4">
            <!-- Header -->
            <div class="border-b-2 border-slate-900 pb-4 mb-6">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-500">Official Guest Attendance Sheet</span>
                        <h1 class="text-2xl font-black text-slate-900 mt-1">{{ event.title }}</h1>
                        <p class="text-xs text-slate-600 mt-1">
                            📅 {{ event.starts_at }}
                            <span v-if="event.ethiopian_date" class="ms-1 font-semibold text-slate-800">({{ event.ethiopian_date.formatted }})</span>
                        </p>
                        <p v-if="event.venue_name" class="text-xs text-slate-600 mt-0.5">
                            📍 {{ event.venue_name }} <span v-if="event.venue_address">• {{ event.venue_address }}</span>
                        </p>
                        <p v-if="event.host_names" class="text-xs text-slate-500 mt-0.5">
                            Hosted by: {{ event.host_names }}
                        </p>
                    </div>

                    <!-- Summary Stats Box -->
                    <div class="text-right text-xs space-y-1 font-mono">
                        <div>Total Registered: <strong>{{ totals.total }}</strong></div>
                        <div>RSVP Attending: <strong>{{ totals.attending }}</strong></div>
                        <div>Expected Headcount: <strong>{{ totals.expected_headcount }}</strong></div>
                        <div>Checked In: <strong>{{ totals.checked_in }}</strong></div>
                    </div>
                </div>
            </div>

            <!-- Guest List Table -->
            <table class="w-full text-xs text-left border-collapse">
                <thead>
                    <tr class="border-b-2 border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-700">
                        <th class="py-2 px-2 w-8 text-center">#</th>
                        <th class="py-2 px-2">Guest Name</th>
                        <th class="py-2 px-2">Phone</th>
                        <th class="py-2 px-2 text-center">RSVP</th>
                        <th class="py-2 px-2 text-center">Party</th>
                        <th class="py-2 px-2 text-center">Status</th>
                        <th class="py-2 px-2 text-center">Arrived</th>
                        <th class="py-2 px-2 w-36">Notes / Signature</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="(g, index) in guests" :key="g.id" class="break-inside-avoid">
                        <td class="py-2 px-2 text-center font-mono text-slate-400 text-[11px]">{{ index + 1 }}</td>
                        <td class="py-2 px-2 font-bold text-slate-900">{{ g.name }}</td>
                        <td class="py-2 px-2 font-mono text-[11px] text-slate-600">{{ g.phone || '—' }}</td>
                        <td class="py-2 px-2 text-center uppercase font-semibold text-[10px]"
                            :class="g.rsvp_status === 'yes' ? 'text-emerald-700' : 'text-slate-500'"
                        >
                            {{ g.rsvp_status }}
                        </td>
                        <td class="py-2 px-2 text-center font-bold">{{ g.rsvp_party_size }}</td>
                        <td class="py-2 px-2 text-center">
                            <span v-if="g.checked_in" class="font-bold text-emerald-700">✓ IN</span>
                            <span v-else class="text-slate-400">[ &nbsp; ]</span>
                        </td>
                        <td class="py-2 px-2 text-center font-mono text-[11px] text-slate-500">
                            {{ g.checked_in_at || '—' }}
                        </td>
                        <td class="py-2 px-2 border-b border-dashed border-slate-300 text-[10px] text-slate-500 italic">
                            {{ g.rsvp_note || '' }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Footer Signoff -->
            <div class="mt-12 pt-6 border-t border-slate-300 flex items-center justify-between text-xs text-slate-500">
                <div>Generated on {{ new Date().toLocaleString() }} • Teri System</div>
                <div class="flex items-center gap-6">
                    <span>Door Staff Signature: _______________________</span>
                </div>
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
    body {
        background: white;
        color: black;
    }
}
</style>
