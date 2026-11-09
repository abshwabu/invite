<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Chart from 'chart.js/auto';

const props = defineProps({
    overview: {
        type: Object,
        required: true,
    },
    byEvent: {
        type: Array,
        default: () => [],
    },
    byKind: {
        type: Array,
        default: () => [],
    },
    byChannel: {
        type: Array,
        default: () => [],
    },
    monthlyTimeline: {
        type: Array,
        default: () => [],
    },
    recentTransactions: {
        type: Array,
        default: () => [],
    },
});

const monthlyCanvas = ref(null);
let monthlyChart = null;

onMounted(() => {
    if (monthlyCanvas.value && props.monthlyTimeline?.length > 0) {
        monthlyChart = new Chart(monthlyCanvas.value, {
            type: 'bar',
            data: {
                labels: props.monthlyTimeline.map(m => m.month),
                datasets: [
                    {
                        label: 'Cards Used',
                        data: props.monthlyTimeline.map(m => m.cards),
                        backgroundColor: '#F59E0B',
                        borderRadius: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
                plugins: {
                    legend: { display: false },
                },
            },
        });
    }
});
</script>

<template>
    <Head title="My Usage • Quota & Card Analytics" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        📊 {{ $t('My Usage & Card Ledger') }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Detailed breakdown of cards consumed per event, channel, and campaign type.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('plan.index')"
                        class="px-3.5 py-1.5 text-xs font-bold rounded-xl bg-amber-500 hover:bg-amber-600 text-white shadow-sm transition"
                    >
                        Manage Subscription & Plan →
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Quota Overview Row -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="p-5 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Available Balance</span>
                    <p class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-2">{{ overview.available }}</p>
                    <span class="text-xs text-gray-400">cards remaining</span>
                </div>

                <div class="p-5 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Consumed</span>
                    <p class="text-3xl font-black text-amber-600 dark:text-amber-400 mt-2">{{ overview.used }}</p>
                    <span class="text-xs text-gray-400">cards spent to date</span>
                </div>

                <div class="p-5 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Package Capacity</span>
                    <p class="text-3xl font-black text-gray-900 dark:text-white mt-2">{{ overview.total }}</p>
                    <span class="text-xs text-gray-400">cumulative cards</span>
                </div>

                <div class="p-5 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Active Plan</span>
                    <p class="text-3xl font-black text-indigo-600 dark:text-indigo-400 mt-2 capitalize">
                        {{ overview.tier ? overview.tier.name_en : 'Free' }}
                    </p>
                    <span class="text-xs text-gray-400">Tier level</span>
                </div>
            </div>

            <!-- Monthly Chart & Channel Distribution -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Monthly Timeline Bar Chart -->
                <div class="lg:col-span-8 bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-1">
                        Monthly Card Usage Timeline
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                        Cards dispatched over the past 6 months
                    </p>
                    <div class="h-64 relative">
                        <canvas ref="monthlyCanvas"></canvas>
                    </div>
                </div>

                <!-- By Channel & Kind -->
                <div class="lg:col-span-4 space-y-6">
                    <!-- By Channel -->
                    <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3">
                            Usage by Delivery Channel
                        </h3>
                        <div class="space-y-3">
                            <div v-for="ch in byChannel" :key="ch.channel">
                                <div class="flex items-center justify-between text-xs font-semibold mb-1">
                                    <span class="text-gray-700 dark:text-gray-300">{{ ch.label }}</span>
                                    <span class="text-gray-900 dark:text-white">{{ ch.cards }} cards ({{ ch.percentage }}%)</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                                    <div
                                        class="h-2 rounded-full"
                                        :class="ch.channel === 'sms' ? 'bg-blue-500' : 'bg-emerald-500'"
                                        :style="{ width: `${ch.percentage}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- By Kind -->
                    <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3">
                            Usage by Campaign Type
                        </h3>
                        <div class="space-y-2">
                            <div
                                v-for="kind in byKind"
                                :key="kind.kind"
                                class="flex items-center justify-between p-2 rounded-xl bg-gray-50 dark:bg-gray-900/40 text-xs"
                            >
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ kind.label }}</span>
                                <span class="font-mono font-bold text-gray-900 dark:text-white">{{ kind.cards }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cards Used Per Event -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">
                    Cards Used per Event
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    Cumulative cards allocated and delivered for each event
                </p>

                <div v-if="byEvent.length === 0" class="p-8 text-center text-xs text-gray-400">
                    No card consumption recorded for specific events yet.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="text-[11px] text-gray-400 uppercase tracking-wider border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="pb-3 font-semibold">Event Title</th>
                                <th class="pb-3 font-semibold">Status</th>
                                <th class="pb-3 font-semibold text-right">Cards Used</th>
                                <th class="pb-3 font-semibold text-right">% of Total</th>
                                <th class="pb-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="ev in byEvent" :key="ev.event_id" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20">
                                <td class="py-3 font-bold text-gray-900 dark:text-white">
                                    {{ ev.title }}
                                </td>
                                <td class="py-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                        :class="ev.status === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'"
                                    >
                                        {{ ev.status }}
                                    </span>
                                </td>
                                <td class="py-3 text-right font-mono font-bold text-amber-600 dark:text-amber-400">
                                    {{ ev.cards }}
                                </td>
                                <td class="py-3 text-right font-semibold text-gray-500">
                                    {{ ev.percentage }}%
                                </td>
                                <td class="py-3 text-right">
                                    <Link
                                        :href="route('events.show', ev.event_id)"
                                        class="text-amber-600 dark:text-amber-400 font-semibold hover:underline"
                                    >
                                        Overview →
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Ledger Transactions -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">
                    Recent Quota Ledger Transactions
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    Audit trail of card charges and refunds
                </p>

                <div v-if="recentTransactions.length === 0" class="p-8 text-center text-xs text-gray-400">
                    No ledger transactions recorded yet.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="text-[11px] text-gray-400 uppercase tracking-wider border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="pb-3 font-semibold">Event</th>
                                <th class="pb-3 font-semibold">Kind</th>
                                <th class="pb-3 font-semibold">Channel</th>
                                <th class="pb-3 font-semibold text-right">Quantity</th>
                                <th class="pb-3 font-semibold text-right">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-mono">
                            <tr v-for="tx in recentTransactions" :key="tx.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20">
                                <td class="py-2.5 font-sans font-medium text-gray-900 dark:text-white">
                                    {{ tx.event_title }}
                                </td>
                                <td class="py-2.5 capitalize text-gray-600 dark:text-gray-300">
                                    {{ tx.kind }}
                                </td>
                                <td class="py-2.5 uppercase text-gray-500">
                                    {{ tx.channel }}
                                </td>
                                <td class="py-2.5 text-right font-bold"
                                    :class="tx.is_refund ? 'text-emerald-600' : 'text-rose-600'"
                                >
                                    {{ tx.is_refund ? tx.quantity : `-${tx.quantity}` }}
                                </td>
                                <td class="py-2.5 text-right font-sans text-gray-400 text-[11px]">
                                    {{ tx.created_at_exact }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
